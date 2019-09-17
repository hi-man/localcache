<?php
namespace Tests;

use LocalCache\LocalCache;
use PHPUnit\Framework\TestCase;

final class LocalCacheTest extends TestCase
{
    private $localcache;

    protected function setUp()
    {
        $this->localcache = new LocalCache(
            'localhost',
            'yacPrefixTesting',
            6379,
            3,
            500000,
            3,
            3,
            0
        );
    }

    public function testGetDefaultValue()
    {
        $value = 'default_value';
        $this->localcache->select(3);
        $this->localcache->clear();

        $ret = $this->localcache->get('key_doest_not_exists', $value);
        $this->assertTrue($ret === false);
    }

    public function testGetValueWithExpiretime()
    {
        $this->localcache->select(3);
        $this->localcache->clear();

        $key = 'getValueWithExpiretime';
        $value = 'getValueWithExpiretimeValue';
        $ttl = 2;

        $this->localcache->set($key, $value, $ttl);
        sleep($ttl + 1);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === false);

        $this->localcache->set($key, $value, $ttl);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === $value);
        sleep($ttl + 1);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === false);
    }

    public function testGetValueWithoutExpiretime()
    {
        $this->localcache->select(3);
        $this->localcache->clear();

        $key = 'getValueWithoutExpiretime';
        $value = 'getValueWithoutExpiretimeValue';

        $this->localcache->set($key, $value);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === $value);
    }

    public function testDeleteKey()
    {
        $this->localcache->select(3);
        $this->localcache->clear();

        $key = 'deletekey';
        $value = 'deletekeyvalue';

        $this->localcache->set($key, $value);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === $value);
        $this->localcache->delete($key);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === false);
    }

    public function testClear()
    {
        $this->localcache->select(3);
        $this->localcache->clear();

        $key = 'deletekey';
        $value = 'deletekeyvalue';

        $this->localcache->set($key, $value);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === $value);
        $this->localcache->clear();
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === false);
    }

    public function testInvalidRedisHostException()
    {
        $ret = null;

        try {
            $lc = new LocalCache(
                '222.222.222.222',
                'yacPrefixTesting',
                6379,
                3,
                500000,
                3,
                3,
                0
            );
            $lc->select(3);
            $ret = $lc->get('somethingNotExists');
        } catch (\RedisException $e) {
            return $this->assertTrue(true);
        }

        return $this->assertTrue(false);
    }
}
