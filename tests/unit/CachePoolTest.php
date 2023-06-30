<?php

namespace Dhii\RedisCache\Test\Unit;

use Dhii\RedisCache\CachePool as Subject;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;
use RuntimeException;

class CachePoolTest extends TestCase
{
    public function testGet()
    {
        $redis = $this->getNewRedis();
        $subject = $this->createSubject($redis);
        $key = uniqid('mykey');
        $value = uniqid('myval');
        $default = uniqid('default');

        $redis->expects($this->exactly(1))
            ->method('get')
            ->with(
                $this->equalTo($key)
            )
            ->will($this->returnValue($value));

        $result = $subject->get($key, $default);
        $this->assertEquals($value, $result);
    }
    public function testSet()
    {
        $redis = $this->getNewRedis();
        $subject = $this->createSubject($redis);
        $key = uniqid('mykey');
        $value = uniqid('myval');
        $ttl = rand(1, 9999);
        $options = [
            'EX' => $ttl,
        ];

        $redis->expects($this->exactly(1))
            ->method('set')
            ->with(
                $this->equalTo($key),
                $this->equalTo($value),
                $this->equalTo($options)
            )
            ->will($this->returnValue($value));

        $subject->set($key, $value, $ttl);
    }

    public function testDelete()
    {
        $redis = $this->getNewRedis();
        $subject = $this->createSubject($redis);
        $key = uniqid('mykey');

        $redis->expects($this->exactly(1))
            ->method('del')
            ->with($this->equalTo($key));

        $subject->delete($key);
    }

    public function testGetMultiple()
    {
        $nonExistentKey = uniqid('nonexistent');
        $values = [
            uniqid('key1') => uniqid('value1'),
            uniqid('key2') => uniqid('value2'),
            $nonExistentKey => null,
        ];
        $default = uniqid('default');
        $expected = array_merge($values, [$nonExistentKey => $default]);
        $redis = $this->getNewRedis();
        $subject = $this->createSubject($redis);

        $redis->expects($this->exactly(1))
            ->method('multi')
            ->will($this->returnValue($redis));
        $redis->expects($this->exactly(count($values)))
            ->method('get');
        $redis->expects($this->exactly(1))
            ->method('exec')
            ->will($this->returnValue(array_values($values)));

        $result = $subject->getMultiple(array_keys($values), $default);
        $this->assertTrue(array_key_exists($nonExistentKey, $result));
        $this->assertEqualsCanonicalizing($expected, $result);
    }

    public function testSetMultiple()
    {
        $nonExistentKey = uniqid('nonexistent');
        $values = [
            uniqid('key1') => uniqid('value1'),
            uniqid('key2') => uniqid('value2'),
            $nonExistentKey => null,
        ];
        $ttl = rand(1, 9999);
        $redis = $this->getNewRedis();
        $subject = $this->createSubject($redis);

        $redis->expects($this->exactly(1))
            ->method('multi')
            ->will($this->returnValue($redis));
        $redis->expects($this->exactly(count($values)))
            ->method('set')
            ->will($this->returnCallback(function (string $key, $value, array $options) use ($values, $ttl) {
                $this->assertArrayHasKey($key, $values);
                $this->assertArrayHasKey('EX', $options);
                $this->assertEquals($options['EX'], $ttl);
            }));
        $redis->expects($this->exactly(1))
            ->method('exec');

        $subject->setMultiple($values, $ttl);
    }

    public function testDeleteMultiple()
    {
        $keys = [
            uniqid('key1'),
            uniqid('key2'),
        ];
        $redis = $this->getNewRedis();
        $subject = $this->createSubject($redis);

        $redis->expects($this->exactly(1))
            ->method('del')
            ->with(
                $this->equalTo($keys)
            );

        $subject->deleteMultiple($keys);
    }

    public function testHas()
    {
        $key = uniqid('key');
        $redis = $this->getNewRedis();
        $subject = $this->createSubject($redis);

        $redis->expects($this->exactly(1))
            ->method('exists')
            ->with(
                $this->equalTo($key)
            );

        $subject->has($key);
    }

    /**
     * Retrieves a new cache pool.
     *
     * @param Redis $redis An open connection to an empty Redis database.
     *
     * @return Subject|MockObject The cache pool.
     *
     * @throws RuntimeException If problem retrieving.
     */
    protected function createSubject(Redis $redis): Subject
    {
        $subject = $this->getMockBuilder(Subject::class)
            ->setConstructorArgs([$redis])
            ->enableProxyingToOriginalMethods()
            ->getMock();

        return $subject;
    }

    /**
     * Retrieves a connection to a new empty Redis database.
     *
     * @return Redis|MockObject The connection.
     *
     * @throws RedisException If problem retrieving.
     */
    protected function getNewRedis(): Redis
    {
        $redis = $this->getMockBuilder(Redis::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $redis;
    }
}
