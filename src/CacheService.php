<?php
namespace LocalCache;

abstract class CacheService
{
    /**
     * redis key prefix
     */
    protected $prefix = '';

    /**
     * default database index is 0
     */
    protected $db = 0;

    /**
     * redis configuration name
     */
    protected $connection = 'default';

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

    /**
     * log redis exception
     *
     * @param InvalidArgumentException|RedisException $e
     * @return none
     */
    abstract protected function logException($e);

    public function setCacheValue(string $key, $value, int $ttl = 0)
    {
        try {
            return CachePoolService::setCacheValue(
                $this->connection,
                $this->db,
                $this->prefix . $key,
                $value,
                $ttl,
                $this->useLocalCache
            );
        } catch (\Exception $e) {
            $this->doLogException($e);
        }

        return false;
    }

    public function getCacheValue(string $key, $defaultValue = null)
    {
        try {
            return CachePoolService::getCacheValue(
                $this->connection,
                $this->db,
                $this->prefix . $key,
                $defaultValue,
                $this->useLocalCache
            );
        } catch (\Exception $e) {
            $this->doLogException($e);
        }

        return false;
    }

    public function deleteByKey(string $key)
    {
        try {
            return CachePoolService::deleteByKey(
                $this->connection,
                $this->db,
                $this->prefix . $key,
                $this->useLocalCache
            );
        } catch (\Exception $e) {
            $this->doLogException($e);
        }

        return false;
    }

    public function __call(string $name, array $arguments)
    {
        $instance = CachePoolService::getCacheInstance(
            $this->connection,
            $this->useLocalCache
        );
        if (empty($instance)) {
            return false;
        }

        try {
            $instance->select($this->db);
            return call_user_func([$instance, $name], ...$arguments);
        } catch (\Exception $e) {
            $this->doLogException($e);
        }

        return false;
    }

    private function doLogException(\Exception $e)
    {
        try {
            $this->logException($e);
        } catch (\Exception $e) {
            // do nothing
            assert(true);
        }
    }
}
