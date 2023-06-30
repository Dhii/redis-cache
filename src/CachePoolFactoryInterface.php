<?php

declare(strict_types=1);

namespace Dhii\RedisCache;

use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * Can create a new cache pool
 */
interface CachePoolFactoryInterface
{
    /**
     * Creates a new cache pool
     *
     * @return CacheInterface The new cache pool.
     *
     * @throws RuntimeException If problem creating.
     */
    public function createCachePool(): CacheInterface;
}
