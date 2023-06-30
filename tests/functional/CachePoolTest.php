<?php

namespace Dhii\RedisCache\Test\Func;

use Dhii\RedisCache\CachePool as Subject;
use Exception;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;
use RuntimeException;

class CachePoolTest extends TestCase
{
    protected Redis $redis;

    /**
     * Skipping all tests in suite if no Redis available.
     */
    public function setUp(): void
    {
        try {
            $this->redis = $this->getNewRedis();
        } catch (Exception $e) {
            $this->markTestSkipped('Redis not available');
        }
    }

    /**
     * Set a single key.
     *
     * Clears the database.
     */
    public function testSet()
    {
        $this->expectNotToPerformAssertions();
        $data = $this->provideData();
        $case = $data['single_key'];
        $redis = $this->redis;
        $redis->flushDB(); // Ensure DB is clean
        $subject = $this->createSubject($redis);
        $key = array_keys($case)[0];
        $value = $case[$key];
        $subject->set($key, $value);

        return $subject;
    }

    /**
     * Check for the existence of a single key, set previously.
     *
     * @depends testSet
     */
    public function testHas()
    {
        $data = $this->provideData();
        $case = $data['single_key'];
        $redis = $this->redis;
        $subject = $this->createSubject($redis);
        $key = array_keys($case)[0];

        $result = $subject->has($key);
        $this->assertTrue($result);

        return $subject;
    }

    /**
     * Check the value of a key set previously.
     *
     * @depends testSet
     */
    public function testGet(Subject $subject)
    {
        $data = $this->provideData();
        $case = $data['single_key'];
        $key = array_keys($case)[0];
        $value = $case[$key];
        $default = uniqid('default');

        $result = $subject->get($key, $default);
        $this->assertEquals($value, $result);

        return $subject;
    }

    /**
     * Delete a single key set previously.
     *
     * @depends testGet
     */
    public function testDelete(Subject $subject)
    {
        $this->expectNotToPerformAssertions();
        $data = $this->provideData();
        $case = $data['single_key'];
        $key = array_keys($case)[0];

        $subject->delete($key);
    }

    /**
     * Check that a single key deleted previously does not exist.
     *
     * @depends testDelete
     */
    public function testHasNot()
    {
        $data = $this->provideData();
        $case = $data['single_key'];
        $redis = $this->redis;
        $subject = $this->createSubject($redis);
        $key = array_keys($case)[0];

        $result = $subject->has($key);
        $this->assertFalse($result);

        return $subject;
    }

    /**
     * Set values for multiple keys.
     *
     * Clears the database.
     */
    public function testSetMultiple()
    {
        $this->expectNotToPerformAssertions();
        $data = $this->provideData();
        $values = $data['multiple_keys'];
        $redis = $this->redis;
        $redis->flushDB(); // Ensure DB is clean
        $subject = $this->createSubject($redis);

        $subject->setMultiple($values);

        return $subject;
    }

    /**
     * Retrieve multiple keys set previously.
     *
     * @depends testSetMultiple
     */
    public function testGetMultiple(Subject $subject)
    {
        $data = $this->provideData();
        $values = $data['multiple_keys'];

        $result = $subject->getMultiple(array_keys($values));
        $this->assertEqualsCanonicalizing($values, $result);

        return $subject;
    }

    /**
     * Delete multiple keys set previously.
     *
     * @depends testGetMultiple
     */
    public function testDeleteMultiple(Subject $subject)
    {
        $this->expectNotToPerformAssertions();
        $data = $this->provideData();
        $values = $data['multiple_keys'];

        $subject->deleteMultiple(array_keys($values));

        return $subject;
    }

    /**
     * Checks if database is empty after clearing.
     *
     * Flushes and re-populates the database.
     */
    public function testSetClear()
    {
        $data = $this->provideData();
        $values = $data['multiple_keys'];
        $redis = $this->redis;
        $redis->flushDB(); // Ensure DB is clean
        $subject = $this->createSubject($redis);

        // Populate data
        $redis = $redis->multi();
        foreach ($values as $key => $value) {
            $redis->set($key, $value);
        }
        $redis->exec();

        // Make sure keys in DB
        $keys = iterator_to_array($this->getRedisKeys($redis));
        $this->assertEqualsCanonicalizing(array_keys($values), $keys);

        // Make sure no keys in DB
        $subject->clear();
        $keys = iterator_to_array($this->getRedisKeys($redis));
        $this->assertEmpty($keys);
    }

    /**
     * Provides a structure with centralized data.
     *
     * @return array{single_key: array<string, mixed>, multiple_keys: list<array<string, mixed>>}
     */
    public function provideData(): array
    {
        static $data = null;

        if ($data === null) {
            $data = [
                'single_key' => [uniqid('mykey') => uniqid('myvalue')],
                'multiple_keys' => [
                    uniqid('key1') => uniqid('val1'),
                    uniqid('key2') => uniqid('val2'),
                    uniqid('key3') => uniqid('val3'),
                ],
            ];
        }

        return $data;
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

        return $redis;
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
