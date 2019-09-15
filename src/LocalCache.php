<?php

namespace LocalCache;

use Psr\SimpleCache\CacheInterface;

/**
 * Class localcache
 */
class LocalCache implements CacheInterface
{
    // redis database lowest index
    const REDIS_DB_INDEX_MIN = 0;
    // redis database highest index
    const REDIS_DB_INDEX_MAX = 15;
    // invalid redis database index
    const REDIS_DB_INDEX_INVALID = -1;
    // max yac key len
    const YAC_KEY_MAX_LENGTH = 48;
    // magic value in localcache
    const NO_DATA_IN_CACHE = 'dvn93j_Ne852_D39dnvbu_A3dfoe';

    /**
     * redis server host
     */
    private $host;

    /**
     * redis server port
     */
    private $port;

    /**
     * connection timeout in second
     */
    private $connTimeout = 3;

    /**
     * retry interval in second
     */
    private $retryInterval = 100;

    /**
     * read timeout in second
     */
    private $readTimeout;

    /**
     * is null when $retryInterval > 0
     */
    private $reserved;

    /**
     * max retry
     */
    private $maxRetry;

    /**
     * current redis database index
     */
    private $currentDb = self::REDIS_DB_INDEX_INVALID;

    /**
     * Yac instance
     */
    private $yac = null;

    /**
     * ttl in local cache
     */
    private $localCacheTimeout = 60;

    /**
     * max length of key
     * if key length >= maxKeyLength, will not be cached
     */
    private $maxKeyLength = self::YAC_KEY_MAX_LENGTH;

    /**
     * initialize
     */
    public function __construct(
        string $host,
        string $yacPrefix = '',
        int $port = 6379,
        int $connTimeout = 3,
        int $retryInterval = 500000,
        int $readTimeout = 3,
        int $maxRetry = 3,
        int $reserved = 0
    ) {
        if (!extension_loaded('Redis')) {
            throw new \InvalidArgumentException('phpredis extension not installed');
        }

        if (!empty($yacPrefix) && !extension_loaded('yac')) {
            throw new \InvalidArgumentException('yac extension not installed');
        }

        $yacKeyPrefixLength = strlen($yacPrefix);
        if (empty($host)
            || $port < 0
            || $connTimeout < 0
            || $retryInterval < 0
            || $readTimeout < 0
            || $yacKeyPrefixLength > self::YAC_KEY_MAX_LENGTH
        ) {
            throw new \InvalidArgumentException('invalid parameter');
        }

        $this->host = $host;
        $this->port = $port;
        $this->connTimeout = $connTimeout;
        $this->retryInterval = $retryInterval;
        $this->readTimeout = $readTimeout;
        $this->reserved = $retryInterval > 0 ? null : $reserved;
        $this->maxRetry = $maxRetry + 1;

        if ($yacKeyPrefixLength > 0) {
            $this->yac = new \Yac($yacPrefix);
            $this->maxKeyLength = self::YAC_KEY_MAX_LENGTH - $yacKeyPrefixLength;
        }
    }

    public function __destruct()
    {
        if (!empty($this->instance)) {
            foreach ($this->instance as $rds) {
                $rds->close();
            }
        }
    }

    public function select(int $dbIndex)
    {
        if ($dbIndex < self::REDIS_DB_INDEX_MIN
            || $dbIndex > self::REDIS_DB_INDEX_MAX
        ) {
            throw new \InvalidArgumentException('invalid redis database index');
        }

        if (!empty($this->instance[$dbIndex])) {
            $this->currentDb = $dbIndex;
            return true;
        }

        $this->currentDb = $dbIndex;
        return true;
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException(
                "invalid parameter, key:{$key}"
            );
        }

        $ret = $this->yacGet($key);
        if ($ret !== false) {
            return ($ret === self::NO_DATA_IN_CACHE) ? $default : $ret;
        }

        $ret = $this->executeCmd('get', [$key]);
        $this->yacSet(
            $key,
            ($ret === false) ? self::NO_DATA_IN_CACHE : $ret,
            $this->localCacheTimeout
        );

        return $ret;
    }

    public function setLocalCacheTimeout(int $timeout)
    {
        if ($timeout <= 0) {
            return false;
        }

        $this->localCacheTimeout = $timeout;
        return true;
    }

    public function getLocalCacheTimeout()
    {
        return $this->localCacheTimeout;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException(
                "invalid parameters, key:{$key}, value:{$value}"
            );
        }

        $this->yacDelete($key);
        if ($value === false) {
            $this->yacSet($key, self::NO_DATA_IN_CACHE, intval($ttl));
            return true;
        }

        $ret = false;
        if (is_null($ttl) || $ttl <= 0) {
            $ret = $this->executeCmd('set', [$key, $value]);
        } else {
            $ret = $this->executeCmd('setex', [$key, $ttl, $value]);
        }

        $ret && $this->yacSet($key, $value, intval($ttl));
        return $ret;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException("invalid parameter, key:{$key}");
        }

        $this->yacDelete($key);
        return $this->executeCmd('delete', [$key]);
    }

    public function expire(string $key, int $seconds)
    {
        if (empty($key) || $seconds <= 0) {
            return false;
        }

        $ret = $this->executeCmd('expire', [$key, $seconds]);
        $ret && $this->yacExpire($key, $seconds);

        return $ret;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        if ($this->yac) {
            $this->yac->flush();
        }

        return $this->executeCmd('flushdb');
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs.
     *      Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        // NOT SUPPORTED yet
        return false;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if $values is neither an array
     *   nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        // NOT SUPPORTED yet
        return false;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        // NOT SUPPORTED
        return false;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException("invalid parameter, key:{$key}");
        }

        return $this->executeCmd('exists', [$key]);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->executeCmd($name, $arguments);
    }

    private function initialize(int $dbIndex, bool $isReconnect = false)
    {
        $rds = null;

        if ($isReconnect) {
            if (empty($this->instance[$dbIndex])) {
                return false;
            }

            $rds = &$this->instance[$dbIndex];
        } else {
            $rds = new \Redis();
        }

        try {
            $ret = $rds->connect(
                $this->host,
                $this->port,
                $this->connTimeout,
                $this->reserved,
                $this->retryInterval,
                $this->readTimeout
            );
        } catch (\RedisException $e) {
            if ($this->retryInterval > 0) {
                usleep($this->retryInterval);
            }

            unset($rds);
            throw $e;
        }

        $rds->select($dbIndex);
        !$isReconnect && ($this->instance[$dbIndex] = $rds);

        return true;
    }

    private function executeCmd(string $method, array $parameters = [])
    {
        if ($this->currentDb === self::REDIS_DB_INDEX_INVALID) {
            throw new \InvalidArgumentException('redis not initialized');
        }

        $exception = null;
        $ret = false;

        for ($retry = 0; $retry < $this->maxRetry; $retry++) {
            $exception = null;
            try {
                if (!isset($this->instance[$this->currentDb])) {
                    $ret = $this->initialize($this->currentDb);
                    if (empty($ret)) {
                        continue;
                    }
                }

                $ret = call_user_func(
                    [$this->instance[$this->currentDb], $method],
                    ...$parameters
                );
            } catch (\RedisException $e) {
                if ($this->retryInterval > 0) {
                    usleep($this->retryInterval);
                }

                $exception = $e;
                $ret = $this->initialize($this->currentDb, true);
                continue;
            }

            break;
        }

        if (!is_null($exception)) {
            throw $exception;
        }

        return $ret;
    }

    private function yacGetKey(string $key)
    {
        if (is_null($this->yac)
            || empty($key)
        ) {
            return '';
        }

        $curkey = "{$this->currentDb}_{$key}";
        return (strlen($curkey) > $this->maxKeyLength) ? md5($curkey) : $curkey;
    }

    private function yacSet(string $key, $value, int $ttl)
    {
        $yackey = $this->yacGetKey($key);
        if (empty($yackey)) {
            return false;
        }

        if ($ttl <= 0) {
            $this->yac->set($yackey, $value);
        }

        return $this->yac->set($yackey, $value, $ttl);
    }

    private function yacGet(string $key)
    {
        $yackey = $this->yacGetKey($key);
        if (empty($yackey)) {
            return false;
        }

        return $this->yac->get($yackey);
    }

    private function yacDelete(string $key)
    {
        $yackey = $this->yacGetKey($key);
        if (empty($yackey)) {
            return;
        }

        return $this->yac->delete($yackey);
    }

    private function yacExpire(string $key, int $ttl)
    {
        $yackey = $this->yacGetKey($key);
        if (empty($yackey)) {
            return;
        }

        $v = $this->yac->get($yackey);
        if (empty($v)) {
            return;
        }

        return $this->yac->set($yackey, $v, $ttl);
    }
}
