<?php
namespace LocalCache;

class CachePoolService
{
    /**
     * array of localcache instances
     * array key is redis connection name
     * [
     *      'default' => $LocalCacheInstance
     * ]
     */
    private static $instanceHasCache = [];

    private static $instanceNoCache = [];

    /**
     * get LocalCache Instance
     *
     * @param array $config redis config array. array elements:
     *                      'host' => 'string, MUST return the redis host',
     *                      'port' => 'int, default value is 6379',
     *                      'connectionTimeout' => 'int, default value is 3',
     *                      'retryInterval' => 'int, default value is 500000',
     *                      'readTimeout' => 'int, default value is 3',
     *                      'maxRetry' => 'int, default value is 3',
     *                      'reserved' => 'int, default value is 0',
     * @param bool $useLocalecache set to false will disable locale cache
     * @param string $connection redis connection name
     */
    public static function initCacheInstance(
        array $config,
        bool $useLocalecache = false,
        string $connection = 'default'
    ) {
        if (empty($config['host'])) {
            throw \InvalidArgumentException('unknown host');
        }

        if ($useLocalecache) {
            if (isset(self::$instanceHasCache[$connection])) {
                return self::$instanceHasCache[$connection];
            }

            self::$instanceHasCache[$connection] = new LocalCache(
                $config['host'],
                $connection,
                $config['port'] ?? 6379,
                $config['timeout'] ?? 3,
                $config['retryInterval'] ?? 500000,
                $config['readTimeout'] ?? 3,
                $config['maxRetry'] ?? 3,
                $config['reserved'] ?? 0
            );

            return self::$instanceHasCache[$connection];
        }

        if (isset(self::$instanceNoCache[$connection])) {
            return self::$instanceNoCache[$connection];
        }

        self::$instanceNoCache[$connection] = new LocalCache(
            $config['host'],
            '',
            $config['port'] ?? 6379,
            $config['timeout'] ?? 3,
            $config['retryInterval'] ?? 500000,
            $config['readTimeout'] ?? 3,
            $config['maxRetry'] ?? 3,
            $config['reserved'] ?? 0
        );

        return self::$instanceNoCache[$connection];
    }

    /**
     * get cache instance by connection identifier
     *
     * @param string $connection redis connection identifier
     * @param bool $useLocalecache set true to use local cache
     *
     * @return null|object return LocalCache instance
     */
    public static function getCacheInstance(
        string $connection = 'default',
        bool $useLocalecache = true
    ) {
        if ($useLocalecache) {
            return self::$instanceHasCache[$connection] ?? null;
        }

        return self::$instanceNoCache[$connection] ?? null;
    }

    /**
     * set cache value
     *
     * @param string $connection connection identifier
     * @param int $db database index
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     *
     * @return int|bool
     */
    public static function setCacheValue(
        string $connection,
        int $db,
        string $key,
        $value,
        int $ttl = 0,
        bool $useLocalecache = true
    ) {
        try {
            $rds = self::getCacheInstance($connection, $useLocalecache);
            if (empty($rds)) {
                return false;
            }

            $rds->select($db);
            return $rds->set($key, $value, $ttl);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }

        return false;
    }

    public static function getCacheValue(
        string $connection,
        int $db,
        string $key,
        $defaultValue = null,
        bool $useLocalecache = true
    ) {
        try {
            $rds = self::getCacheInstance($connection, $useLocalecache);
            if (empty($rds)) {
                return false;
            }

            $rds->select($db);
            return $rds->get($key, $defaultValue);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }

        return false;
    }

    public static function deleteByKey(
        string $connection,
        int $db,
        string $key,
        bool $useLocalecache = true
    ) {
        try {
            $rds = self::getCacheInstance($connection, $useLocalecache);
            if (empty($rds)) {
                return false;
            }

            $rds->select($db);
            return $rds->delete($key);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }

        return false;
    }
}
