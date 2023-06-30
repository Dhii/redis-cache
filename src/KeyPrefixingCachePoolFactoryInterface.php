<?php

declare(strict_types=1);

namespace Dhii\RedisCache;

use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * Can create a cache pool with a key prefix.
 */
interface KeyPrefixingCachePoolFactoryInterface
{
    /**
     * Creates a new cache pool with the specified prefix.
     *
     * @param string $prefix The prefix.
     *                       It SHOULD end with a delimiter that separates it from the rest of the alphanumeric key.
     * @return CacheInterface The new cache pool
     *
     * @throws RuntimeException If problem creating.
     */
    public function createCachePoolWithPrefix(string $prefix): CacheInterface;
}
