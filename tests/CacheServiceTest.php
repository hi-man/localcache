<?php
namespace Tests;

use LocalCache\{
    LocalCache,
    CacheService
};
use PHPUnit\Framework\TestCase;

class SampleCache extends CacheService
{
    const PREFIX_FOR_TEST = 'test';

    const CONN_FOR_TEST = 'test_conn';

    const DBIDX_FOR_TEST = 0;

    const EXCEPTION_TRIGGERED = 'here_is_the_exception';

    protected $prefix = self::PREFIX_FOR_TEST;

    protected $connection = self::CONN_FOR_TEST;

    protected $useLocalCache = true;

    protected $db = self::DBIDX_FOR_TEST;

    public $exceptionMsg = '';

    protected function getConfigByConnection(string $connection)
    {
        if ($connection != $this->connection) {
            throw new \Exception('connection name conflicts');
        }

        return [
            'host' => 'localhost',
            'port' => 6379,
            'connectionTimeout' => 1,
            'retryInterval' => 200000,
            'readTimeout' => 1,
            'maxRetry' => 1,
            'reserved' => 0,
        ];
    }

    protected function logException($e)
    {
        assert($e !== false);
        $this->exceptionMsg = self::EXCEPTION_TRIGGERED;
    }
}

// phpcs:ignore
class SampleCacheWithouLocal extends SampleCache
{
    protected $useLocalCache = false;
}

// phpcs:ignore
final class CacheServiceTest extends TestCase
{
    private $cacheWithLocal = null;

    private $cacheWithoutLocal = null;

    private $yac = null;

    private function getYacKey(string $key)
    {
        return SampleCache::DBIDX_FOR_TEST . '_' . SampleCache::PREFIX_FOR_TEST . $key;
    }

    private function getRedisKey($string)
    {
        return SampleCache::PREFIX_FOR_TEST . $string;
    }

    protected function setUp()
    {
        $this->cacheWithLocal = new SampleCache();
        $this->cacheWithoutLocal = new SampleCacheWithouLocal();
        $this->yac = new \Yac(SampleCache::CONN_FOR_TEST);
    }

    public function testSetCacheValueWithLocal()
    {
        $key = 'tscvw';
        $value = 'value_of_tscvw';
        $ttl = 1;
        $defaultValue = 'default_value';

        $ret = $this->cacheWithLocal->setCacheValue($key, $value, $ttl);
        $this->assertTrue($ret !== false);

        $ret = $this->cacheWithLocal->getCacheValue($key);
        $this->assertTrue($ret === $value);

        $ret = $this->cacheWithLocal->get($key);
        $this->assertTrue($ret === $value);

        $ret = $this->yac->get($this->getYacKey($key));
        $this->assertTrue($ret === $value);

        sleep($ttl + 1);
        $ret = $this->cacheWithLocal->getCacheValue($key);
        $this->assertTrue($ret === false);

        $ret = $this->cacheWithLocal->getCacheValue($key, $defaultValue);
        $this->assertTrue($ret === $defaultValue);

        $ret = $this->yac->get($this->getYacKey($key));
        $this->assertTrue(
            $ret === LocalCache::NO_DATA_IN_CACHE || $ret === false
        );
    }

    public function testSetCacheValueWithoutLocal()
    {
        $key = 'tscvw';
        $value = 'value_of_tscvw';
        $ttl = 1;
        $defaultValue = 'default_value';

        $ret = $this->cacheWithoutLocal->setCacheValue($key, $value, $ttl);
        $this->assertTrue($ret !== false);

        $ret = $this->cacheWithoutLocal->getCacheValue($key);
        $this->assertTrue($ret === $value);

        $ret = $this->cacheWithoutLocal->get($key);
        $this->assertTrue($ret === $value);


        $ret = $this->yac->get($this->getYacKey($key));
        $this->assertTrue(
            $ret === LocalCache::NO_DATA_IN_CACHE || $ret === false
        );

        sleep($ttl + 1);
        $ret = $this->cacheWithoutLocal->getCacheValue($key);
        $this->assertTrue($ret === false);

        $ret = $this->cacheWithoutLocal->getCacheValue($key, $defaultValue);
        $this->assertTrue($ret === false);

        $ret = $this->yac->get($this->getYacKey($key));
        $this->assertTrue(
            $ret === LocalCache::NO_DATA_IN_CACHE || $ret === false
        );
    }

    public function testDeleteKeyWithLocal()
    {
        $key = 'tscvw';
        $value = 'value_of_tscvw';

        $this->cacheWithLocal->setCacheValue($key, $value);
        $ret = $this->cacheWithoutLocal->getCacheValue($key);
        $this->assertTrue($ret === $value);

        $this->cacheWithLocal->deleteByKey($key);
        $ret = $this->cacheWithoutLocal->getCacheValue($key);
        $this->assertTrue($ret === false);
        $ret = $this->yac->get($this->getYacKey($key));
        $this->assertTrue($ret === false);
    }

    public function testDeleteKeyWithoutLocal()
    {
        $key = 'tscvw';
        $value = 'value_of_tscvw';

        $this->cacheWithoutLocal->setCacheValue($key, $value);
        $ret = $this->cacheWithoutLocal->getCacheValue($key);
        $this->assertTrue($ret === $value);

        $this->cacheWithoutLocal->deleteByKey($key);
        $ret = $this->cacheWithoutLocal->getCacheValue($key);
        $this->assertTrue($ret === false);
        $ret = $this->yac->get($this->getYacKey($key));
        $this->assertTrue($ret === false);
    }

    public function testException()
    {
        $this->cacheWithLocal->redisCommandDoesNotExists();
        $this->assertTrue(
            $this->cacheWithLocal->exceptionMsg == SampleCache::EXCEPTION_TRIGGERED
        );
    }

    public function testRedisCmdWorking()
    {
        $key = 'tscvw';
        $value = 'value_of_tscvw';

        $this->cacheWithLocal->set($key, $value);
        $ret = $this->cacheWithLocal->get($key);
        $this->assertTrue($ret === $value);
    }
}
