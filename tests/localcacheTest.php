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

        $this->localcache->setLocalCacheTimeout(3);
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

    public function testHashSingle()
    {
        $this->localcache->select(3);
        $this->localcache->clear();

        $key = 'hashkey';
        $fieldkey = 'fieldkey';
        $value = 'value';

        $ret = $this->localcache->hSet($key, $fieldkey, $value);
        $this->assertTrue($ret !== false);
        $ret = $this->localcache->hGet($key, $fieldkey);
        $this->assertTrue($ret === $value);

        sleep($this->localcache->getLocalCacheTimeout() + 1);
        $ret = $this->localcache->hGet($key, $fieldkey);
        $this->assertTrue($ret === $value);
        $ret = $this->localcache->hGet($key, $fieldkey);
        $this->assertTrue($ret === $value);
    }

    public function testHashMultiple()
    {
        $this->localcache->select(3);
        $this->localcache->clear();

        $key = 'hashkeymulti';
        $fieldkeyA = 'fieldkeyA';
        $valueA = 'valueA';
        $fieldkeyB = 'fieldkeyB';
        $valueB = 'valueB';
        $fieldkeyC = 'fieldkeyC';

        $ret = $this->localcache->hMSet(
            $key,
            [
                $fieldkeyA => $valueA,
                $fieldkeyB => $valueB,
            ]
        );
        $this->assertTrue($ret !== false);

        $ret = $this->localcache->hMGet(
            $key,
            [
                $fieldkeyA,
                $fieldkeyB,
            ]
        );
        $this->assertTrue(
            !empty($ret)
            && ($ret[$fieldkeyA] === $valueA)
            && ($ret[$fieldkeyB] === $valueB)
        );

        $ret = $this->localcache->hMGet(
            $key,
            [
                $fieldkeyA,
                $fieldkeyB,
                $fieldkeyC,
            ]
        );
        $this->assertTrue(
            !empty($ret)
            && ($ret[$fieldkeyA] === $valueA)
            && ($ret[$fieldkeyB] === $valueB)
            && empty($ret[$fieldkeyC])
        );

        sleep($this->localcache->getLocalCacheTimeout() + 1);

        $ret = $this->localcache->hMGet(
            $key,
            [
                $fieldkeyA,
                $fieldkeyB,
            ]
        );
        $this->assertTrue(
            !empty($ret)
            && ($ret[$fieldkeyA] === $valueA)
            && ($ret[$fieldkeyB] === $valueB)
        );

        $ret = $this->localcache->hMGet(
            $key,
            [
                $fieldkeyA,
                $fieldkeyB,
            ]
        );
        $this->assertTrue(
            !empty($ret)
            && ($ret[$fieldkeyA] === $valueA)
            && ($ret[$fieldkeyB] === $valueB)
        );

        $ret = $this->localcache->hMGet(
            $key,
            [
                $fieldkeyA,
                $fieldkeyB,
                $fieldkeyC,
            ]
        );
        $this->assertTrue(
            !empty($ret)
            && ($ret[$fieldkeyA] === $valueA)
            && ($ret[$fieldkeyB] === $valueB)
            && empty($ret[$fieldkeyC])
        );
    }

    public function testHashGetAll()
    {
        $this->localcache->select(3);
        $this->localcache->clear();

        $key = 'hashkeygetall';
        $fieldkeyA = 'fieldkeyA';
        $valueA = 'valueA';
        $fieldkeyB = 'fieldkeyB';
        $valueB = 'valueB';
        $fieldkeyC = 'fieldkeyC';
        $valueC = 'valueC';

        $ret = $this->localcache->hMSet(
            $key,
            [
                $fieldkeyA => $valueA,
                $fieldkeyB => $valueB,
                $fieldkeyC => $valueC,
            ]
        );
        $this->assertTrue($ret !== false);

        $ret = $this->localcache->hGetAll($key);
        $this->assertTrue(
            !empty($ret)
            && !empty($ret[$fieldkeyA]) && $ret[$fieldkeyA] === $valueA
            && !empty($ret[$fieldkeyB]) && $ret[$fieldkeyB] === $valueB
            && !empty($ret[$fieldkeyC]) && $ret[$fieldkeyC] === $valueC
        );

        $ret = $this->localcache->hGet($key, $fieldkeyA);
        $this->assertTrue($ret === $valueA);
    }

    public function testHashDel()
    {
        $this->localcache->select(3);
        $this->localcache->clear();

        $key = 'hashkeydel';
        $fieldkeyA = 'fieldkeyA';
        $valueA = 'valueA';
        $fieldkeyB = 'fieldkeyB';
        $valueB = 'valueB';
        $fieldkeyC = 'fieldkeyC';
        $valueC = 'valueC';

        $ret = $this->localcache->hMSet(
            $key,
            [
                $fieldkeyA => $valueA,
                $fieldkeyB => $valueB,
                $fieldkeyC => $valueC,
            ]
        );
        $this->assertTrue($ret !== false);

        $ret = $this->localcache->hDel($key, $fieldkeyA, $fieldkeyB);
        $this->assertTrue(!empty($ret));

        $ret = $this->localcache->hGet($key, $fieldkeyA);
        $this->assertTrue(empty($ret));
        $ret = $this->localcache->hGet($key, $fieldkeyB);
        $this->assertTrue(empty($ret));
        $ret = $this->localcache->hGet($key, $fieldkeyC);
        $this->assertTrue($ret === $valueC);
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
