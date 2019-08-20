<?php
namespace LocalCache;

abstract class CacheService
{
    /**
     * redis key prefix
     */
    protected $prefix = '';

    /**
     * redis configuration name
     */
    protected $connection = 'default';

    /**
     * redis key prefix length
     */
    protected $keyLenConsumed;

    /**
     * set false to disable localcache
     */
    protected $useLocalCache = false;

    public function __construct()
    {
        CachePoolService::initCacheInstance(
            $this->getConfigByConnection($this->connection),
            $this->useLocalCache,
            $this->connection
        );
        $this->keyLenConsumed = strlen($this->connection);
    }

    /**
     * get redis configuration by connection name
     *
     * @param string $connection
     * @return array [
     *      'host' => 'string, MUST return the redis host',
     *      'port' => 'int, default value is 6379',
     *      'connectionTimeout' => 'int, default value is 3',
     *      'retryInterval' => 'int, default value is 500000',
     *      'readTimeout' => 'int, default value is 3',
     *      'maxRetry' => 'int, default value is 3',
     *      'reserved' => 'int, default value is 0',
     * ]
     */
    abstract protected function getConfigByConnection(string $connection);

    public function setCacheValue(string $key, $value, int $ttl = 0)
    {
        return CachePoolService::setCacheValue(
            $this->connection,
            $this->db,
            $this->prefix . $key,
            $value,
            $ttl
        );
    }

    public function getCacheValue(string $key, $defaultValue = null)
    {
        return CachePoolService::getCacheValue(
            $this->connection,
            $this->db,
            $this->prefix . $key,
            $defaultValue
        );
    }

    public function deleteByKey(string $key)
    {
        return CachePoolService::deleteByKey(
            $this->connection,
            $this->db,
            $this->prefix . $key
        );
    }

    public function __call(string $name, array $arguments)
    {
        $instance = CachePoolService::getCacheInstance($this->connection);
        if (empty($instance)) {
            return false;
        }

        $instance->select($this->db);
        return call_user_func([$instance, $name], ...$arguments);
    }
}
