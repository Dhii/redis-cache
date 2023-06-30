<?php

declare(strict_types=1);

namespace Dhii\RedisCache;

use Psr\SimpleCache\CacheInterface;
use Redis;

/**
 * Creates a new cache pool with a key prefix.
 */
class KeyPrefixingCachePoolFactory implements KeyPrefixingCachePoolFactoryInterface
{
    protected CachePoolFactoryInterface $cachePoolFactory;
    protected Redis $redis;

    public function __construct(CachePoolFactoryInterface $cachePoolFactory, Redis $redis)
    {
        $this->cachePoolFactory = $cachePoolFactory;
        $this->redis = $redis;
    }

    /**
     * @inheritDoc
     */
    public function createCachePoolWithPrefix(string $prefix): CacheInterface
    {
        $redis = $this->redis;
        $factory = $this->cachePoolFactory;
        $innerPool = $factory->createCachePool();

        return new KeyPrefixingCachePool($innerPool, $prefix, $redis);
    }
}
