<?php

declare(strict_types=1);

namespace Dhii\RedisCache\Test\Func;

use Andrew\Proxy;
use Dhii\RedisCache\CachePoolFactory as Subject;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;

class CachePoolFactoryTest extends TestCase
{
    /**
     * Create a new cache pool with configured Redis instance.
     */
    public function testCreateCachePool()
    {
        $redis = $this->getNewRedis();
        $subject = new Subject($redis);

        $cachePool = new Proxy($subject->createCachePool());
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
}
