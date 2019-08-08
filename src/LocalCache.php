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
    // max yac prefix len
    const YAC_PREFIX_MAX_LENGTH = 20;
    // magic value in localcache
    const NO_DATA_IN_CACHE = 'dvn93j_Ne852_D39dnvbu_A3dfoe';

    /**
     * redis instance
     *
     * Array key is database index
     */
    private $instance = [];

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

        if (!extension_loaded('yac')) {
            throw new \InvalidArgumentException('yac extension not installed');
        }

        if (empty($host)
            || $port < 0
            || $connTimeout < 0
            || $retryInterval < 0
            || $readTimeout < 0
            || strlen($yacPrefix) > self::YAC_PREFIX_MAX_LENGTH
        ) {
            throw new \InvalidArgumentException('invalid parameter');
        }

        $this->host = $host;
        $this->port = $port;
        $this->connTimeout = $connTimeout;
        $this->retryInterval = $retryInterval;
        $this->readTimeout = $readTimeout;
        $this->reserved = $retryInterval > 0 ? null : $reserved;
        $this->maxRetry = $maxRetry;

        !empty($yacPrefix) && ($this->yac = new \Yac($yacPrefix));
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

        // 实例不存在则创建
        $ret = $this->initialize($dbIndex);
        if (empty($ret)) {
            throw new \InvalidArgumentException(
                "redis connection failed: {$this->host} {$this->port}"
            );
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

        $yacKey = '';
        if ($this->yac) {
            $yacKey = $this->getYacKey($key);
            $ret = $this->yac->get($yacKey);
            if (!empty($ret)) {
                return ($ret === self::NO_DATA_IN_CACHE) ? $default : $ret;
            }
        }

        $ret = $this->executeCmd('get', [$key]);
        if ($this->yac) {
            if ($ret) {
                $this->yac->set($yacKey, $ret, $this->localCacheTimeout);
            } else {
                $this->yac->set(
                    $yacKey,
                    self::NO_DATA_IN_CACHE,
                    3
                );
            }
        }

        return $ret ?: $default;
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
        if (empty($key) || empty($value)) {
            throw new \InvalidArgumentException(
                "invalid parameters, key:{$key}, value:{$value}"
            );
        }

        $yacKey = '';
        if ($this->yac) {
            $yacKey = $this->getYacKey($key);
            $this->yac->delete($yacKey);
        }

        if (is_null($ttl) || $ttl <= 0) {
            return $this->executeCmd('set', [$key, $value]);
        }

        $ret = $this->executeCmd('setex', [$key, $ttl, $value]);
        $ret && $this->yac && $this->yac->set($yacKey, $value, $ttl);

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

        $yacKey = '';
        if ($this->yac) {
            $yacKey = $this->getYacKey($key);
            $this->yac->delete($yacKey);
        }

        return $this->executeCmd('delete', [$key]);
    }

    public function expire(string $key, int $seconds)
    {
        if (empty($key) || $seconds <= 0) {
            return false;
        }

        $ret = $this->executeCmd('expire', [$key, $seconds]);
        $yacKey = '';
        if ($this->yac) {
            $yacKey = $this->getYacKey($key);
        }

        if ($ret === false) {
            $this->yac && $this->yac->delete($key);
            return false;
        }

        if ($this->yac) {
            $value = $this->yac->get($yacKey);
            $this->yac->set($yacKey, $value, $seconds);
        }

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

        for ($retry = 0; $retry < $this->maxRetry; $retry++) {
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

                $exception = $e;
                continue;
            }

            break;
        }
        if (empty($ret)) {
            return false;
        }

        $rds->select($dbIndex);
        !$isReconnect && ($this->instance[$dbIndex] = $rds);

        unset($rds);
        return true;
    }

    private function executeCmd(string $method, array $parameters = [])
    {
        if ($this->currentDb === self::REDIS_DB_INDEX_INVALID
            || empty($this->instance[$this->currentDb])
        ) {
            throw new \InvalidArgumentException('redis not initialized');
        }

        $exception = null;
        $ret = false;

        for ($retry = 0; $retry < $this->maxRetry; $retry++) {
            try {
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

    private function getYacKey($key)
    {
        return "{$this->currentDb}_{$key}";
    }
}
