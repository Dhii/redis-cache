<?php

declare(strict_types=1);

namespace Dhii\RedisCache;

use Psr\SimpleCache\CacheInterface;
use Redis;

/**
 * Creates a new cache pool.
 */
class CachePoolFactory implements CachePoolFactoryInterface
{
    protected Redis $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @inheritDoc
     */
    public function createCachePool(): CacheInterface
    {
        $product = new CachePool($this->redis);

        return $product;
    }
}
