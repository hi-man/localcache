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
            '127.0.0.1',
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
        $this->assertTrue($ret === $value);
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
        $this->assertTrue($ret === null);

        $this->localcache->set($key, $value, $ttl);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === $value);
        sleep($ttl + 1);
        $ret = $this->localcache->get($key);
        $this->assertTrue($ret === null);
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
        $this->assertTrue($ret === null);
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
        $this->assertTrue($ret === null);
    }

    public function testInvalidRedisHostException()
    {
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
        } catch (\InvalidArgumentException $e) {
            return $this->assertTrue(true);
        }

        return $this->assertTrue(false);
    }
}
