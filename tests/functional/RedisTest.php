<?php

namespace Dhii\RedisCache\Test\Func;

use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;

class RedisTest extends TestCase
{
    protected Redis $redis;

    public function setUp(): void
    {
        try {
            $this->redis = $this->getNewRedis();
        } catch (Exception $e) {
            $this->markTestSkipped('Redis not available');
        }
    }

    public function testSetGet()
    {
        $redis = $this->redis;
        $key = 'mykey';
        $value = uniqid('myval');
        $redis->set($key, $value);

        $result = $redis->get($key);
        $this->assertEquals($value, $result);
    }

    public function testRedisKeys()
    {
        $redis = $this->redis;
        $keys = [
            uniqid('key1'),
            uniqid('key2'),
        ];

        $redis = $redis->multi();
        foreach ($keys as $key) {
            $redis->set($key, uniqid('value'));
        }
        $redis->exec();

        $result = iterator_to_array($this->getRedisKeys($redis, '*'));
        $this->assertEqualsCanonicalizing($keys, $result);
    }

    /**
     * Retrieves a connection to a new empty Redis database.
     *
     * @return Redis The connection.
     *
     * @throws RedisException If problem retrieving.
     */
    protected function getNewRedis(): Redis
    {
        $redis = new Redis();
        $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
        $redis->flushDB(false);

        return $redis;
    }

    /**
     * Retrieve all keys from a Redis database.
     *
     * @param Redis $redis The Redis database.
     * @param string $pattern The pattern to match the keys against. Default: all.
     *
     * @return Generator<string> A list of Redis keys.
     *
     * @throws RedisException If problem retrieving.
     */
    protected function getRedisKeys(Redis $redis, string $pattern = '*'): iterable
    {
        $redis->setOption(Redis::OPT_SCAN, REDIS::SCAN_RETRY);

        $i = null;
        while ($curKeys = $redis->scan($i, $pattern)) {
            foreach ($curKeys as $key) {
                yield $key;
            }
        }
    }
}
