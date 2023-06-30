<?php

declare(strict_types=1);

namespace Dhii\RedisCache\Test\Unit;

use Andrew\Proxy;
use Dhii\RedisCache\CachePoolFactoryInterface;
use Dhii\RedisCache\KeyPrefixingCachePoolFactory as Subject;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;

class KeyPrefixingCachePoolFactoryTest extends TestCase
{
    /**
     * Create a new cache pool with configured Redis instance.
     */
    public function testCreateCachePoolWithPrefix()
    {
        $redis = $this->getNewRedis();
        $innerFactory = $this->createCachePoolFactory();
        $subject = new Subject($innerFactory, $redis);
        $namespace = uniqid('namespace');
        $prefix = "${namespace}:";

        $cachePool = new Proxy($subject->createCachePoolWithPrefix($prefix));
        $this->assertSame($redis, $cachePool->redis);
    }

    /**
     * Retrieves a new Redis instance.
     *
     * @return Redis|MockObject The new instance, not suitable for querying.
     */
    protected function getNewRedis(): Redis
    {
        $redis = $this->getMockBuilder(Redis::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $redis;
    }

    /**
     * Creates a new cache pool factory.
     *
     * @return CachePoolFactoryInterface|MockObject The new factory.
     */
    protected function createCachePoolFactory(): CachePoolFactoryInterface
    {
        $mock = $this->getMockBuilder(CachePoolFactoryInterface::class)
            ->getMock();

        return $mock;
    }
}
