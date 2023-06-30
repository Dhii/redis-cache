<?php

namespace Dhii\RedisCache\Test\Unit;

use Dhii\RedisCache\KeyPrefixingCachePool as Subject;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Redis;
use RuntimeException;

class KeyPrefixingCachePoolTest extends TestCase
{
    public function testGet()
    {
        $redis = $this->getNewRedis();
        $internalPool = $this->createCachePool();
        $namespace = uniqid('namespace');
        $prefix = "{$namespace}:";
        $subject = $this->createSubject($internalPool, $prefix, $redis);
        $key = uniqid('mykey');
        $prefixedKey = "{$prefix}{$key}";
        $value = uniqid('myval');
        $default = uniqid('default');

        $internalPool->expects($this->exactly(1))
            ->method('get')
            ->with(
                $this->equalTo($prefixedKey)
            )
            ->will($this->returnValue($value));

        $result = $subject->get($key, $default);
        $this->assertEquals($value, $result);
    }
    public function testSet()
    {
        $redis = $this->getNewRedis();
        $internalPool = $this->createCachePool();
        $namespace = uniqid('namespace');
        $prefix = "{$namespace}:";
        $subject = $this->createSubject($internalPool, $prefix, $redis);
        $key = uniqid('mykey');
        $prefixedKey = "{$prefix}{$key}";
        $value = uniqid('myvalue');
        $ttl = rand(1, 9999);
        $isSuccess = (bool) (rand(1, 100) % 2);

        $internalPool->expects($this->exactly(1))
            ->method('set')
            ->with(
                $this->equalTo($prefixedKey),
                $this->equalTo($value),
                $this->equalTo($ttl)
            )
            ->will($this->returnValue($isSuccess));

        $result = $subject->set($key, $value, $ttl);
        $this->assertEquals($isSuccess, $result);
    }

    public function testDelete()
    {
        $redis = $this->getNewRedis();
        $internalPool = $this->createCachePool();
        $namespace = uniqid('namespace');
        $prefix = "{$namespace}:";
        $subject = $this->createSubject($internalPool, $prefix, $redis);
        $key = uniqid('mykey');
        $prefixedKey = "{$prefix}{$key}";
        $isSuccess = (bool) (rand(1, 100) % 2);

        $internalPool->expects($this->exactly(1))
            ->method('delete')
            ->with($this->equalTo($prefixedKey))
            ->will($this->returnValue($isSuccess));

        $result = $subject->delete($key);
        $this->assertEquals($isSuccess, $result);
    }

    public function testGetMultiple()
    {
        $redis = $this->getNewRedis();
        $internalPool = $this->createCachePool();
        $namespace = uniqid('namespace');
        $prefix = "{$namespace}:";
        $subject = $this->createSubject($internalPool, $prefix, $redis);
        $default = uniqid('default');
        $values = [
            uniqid('key1') => uniqid('val1'),
            uniqid('key2') => uniqid('val2'),
            uniqid('key3') => uniqid('val3'),
        ];
        $keys = array_keys($values);
        $prefixedKeys = array_map(function (string $key) use ($prefix): string {
            return "{$prefix}{$key}";
        }, array_keys($values));
        $prefixedValues = array_combine($prefixedKeys, array_values($values));

        $internalPool->expects($this->exactly(1))
            ->method('getMultiple')
            ->with(
                $this->equalTo($prefixedKeys),
                $this->equalTo($default)
            )
            ->will($this->returnValue($prefixedValues));

        $results = $subject->getMultiple($keys, $default);
        $this->assertEqualsCanonicalizing($values, $results);
    }

    public function testSetMultiple()
    {
        $redis = $this->getNewRedis();
        $internalPool = $this->createCachePool();
        $namespace = uniqid('namespace');
        $prefix = "{$namespace}:";
        $subject = $this->createSubject($internalPool, $prefix, $redis);
        $values = [
            uniqid('key1') => uniqid('val1'),
            uniqid('key2') => uniqid('val2'),
            uniqid('key3') => uniqid('val3'),
        ];
        $prefixedKeys = array_map(function (string $key) use ($prefix): string {
            return "{$prefix}{$key}";
        }, array_keys($values));
        $prefixedValues = array_combine($prefixedKeys, array_values($values));
        $ttl = rand(1, 9999);
        $isSuccess = (bool) (rand(1, 100) % 2);

        $internalPool->expects($this->exactly(1))
            ->method('setMultiple')
            ->with(
                $this->equalTo($prefixedValues),
                $this->equalTo($ttl)
            )
            ->will($this->returnValue($isSuccess));

        $result = $subject->setMultiple($values, $ttl);
        $this->assertEquals($isSuccess, $result);
    }

    public function testDeleteMultiple()
    {
        $redis = $this->getNewRedis();
        $internalPool = $this->createCachePool();
        $namespace = uniqid('namespace');
        $prefix = "{$namespace}:";
        $subject = $this->createSubject($internalPool, $prefix, $redis);
        $keys = [
            uniqid('key1'),
            uniqid('key2'),
            uniqid('key3'),
        ];
        $prefixedKeys = array_map(function (string $key) use ($prefix): string {
            return "{$prefix}{$key}";
        }, $keys);
        $isSuccess = (bool) (rand(1, 100) % 2);

        $internalPool->expects($this->exactly(1))
            ->method('deleteMultiple')
            ->with(
                $this->equalTo($prefixedKeys)
            )
            ->will($this->returnValue($isSuccess));

        $result = $subject->deleteMultiple($keys);
        $this->assertEquals($isSuccess, $result);
    }

    public function testHas()
    {
        $redis = $this->getNewRedis();
        $internalPool = $this->createCachePool();
        $namespace = uniqid('namespace');
        $prefix = "{$namespace}:";
        $subject = $this->createSubject($internalPool, $prefix, $redis);
        $key = uniqid('mykey');
        $prefixedKey = "{$prefix}{$key}";
        $isHas = (bool) (rand(1, 100) % 2);

        $internalPool->expects($this->exactly(1))
            ->method('has')
            ->with(
                $this->equalTo($prefixedKey)
            )
            ->will($this->returnValue($isHas));

        $result = $subject->has($key);
        $this->assertEquals($isHas, $result);
    }

    /**
     * Retrieves a new cache pool.
     *
     * @param CacheInterface $cachePool, Any other cache pool.
     * @param string $prefix A prefix for this subject.
     *
     * @return Subject|MockObject The subject.
     *
     * @throws RuntimeException If problem retrieving.
     */
    protected function createSubject(CacheInterface $cachePool, string $prefix, Redis $redis): Subject
    {
        $subject = $this->getMockBuilder(Subject::class)
            ->setConstructorArgs([$cachePool, $prefix, $redis])
            ->enableProxyingToOriginalMethods()
            ->getMock();

        return $subject;
    }

    /**
     * Creates a new cache pool
     *
     * @return CacheInterface|MockObject The new instance.
     */
    protected function createCachePool(): CacheInterface
    {
        $redis = $this->getMockBuilder(CacheInterface::class)
            ->disableProxyingToOriginalMethods()
            ->getMock();

        return $redis;
    }

    /**
     * Retrieves a connection to a new empty Redis database.
     *
     * @return Redis|MockObject The connection.
     */
    protected function getNewRedis(): Redis
    {
        $redis = $this->getMockBuilder(Redis::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $redis;
    }
}
