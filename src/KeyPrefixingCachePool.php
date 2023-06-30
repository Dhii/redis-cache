<?php

declare(strict_types=1);

namespace Dhii\RedisCache;

use Dhii\RedisCache\Exception\CacheException;
use Exception;
use Generator;
use Psr\SimpleCache\CacheInterface;
use RangeException;
use Redis;
use RedisException;
use RuntimeException;

/**
 * A cache pool wrapper that prefixes/unprefixes keys when necessary.
 */
class KeyPrefixingCachePool implements CacheInterface
{
    protected CacheInterface $cachePool;
    protected string $prefix;
    protected Redis $redis;

    /**
     * @param CacheInterface $cachePool A cache pool, the keys of which to prefix.
     * @param string $prefix The key prefix.
     * @param Redis $redis A connected Redis instance.
     *                     Used to retrieve prefixed keys to {@link clear()}.
     */
    public function __construct(CacheInterface $cachePool, string $prefix, Redis $redis)
    {
        $this->cachePool = $cachePool;
        $this->prefix = $prefix;
        $this->redis = $redis;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        $cachePool = $this->cachePool;
        $realKey = $this->prefixKey($key);

        return $cachePool->get($realKey, $default);
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        $cachePool = $this->cachePool;
        $realKey = $this->prefixKey($key);

        return $cachePool->set($realKey, $value, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        $cachePool = $this->cachePool;
        $realKey = $this->prefixKey($key);

        return $cachePool->delete($realKey);
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $redis = $this->redis;
        $prefix = $this->prefix;
        $cachePool = $this->cachePool;

        try {
            $keys = $this->getRedisKeys($redis, "{$prefix}*");
        } catch (Exception $e) {
            throw new CacheException(sprintf('Could not retrieve keys with prefix "%1$s"', $prefix), 0, $e);
        }

        return $cachePool->deleteMultiple($keys);
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        $cachePool = $this->cachePool;

        $realKeys = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            $realKeys[] = $this->prefixKey($key);
        }

        $results = $cachePool->getMultiple($realKeys, $default);
        $realResults = [];
        foreach ($results as $key => $value) {
            $key = (string) $key;
            $realResults[$this->unprefixKey($key)] = $value;
        }

        return $realResults;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        $cachePool = $this->cachePool;

        $realValues = [];
        foreach ($values as $key => $value) {
            $key = (string) $key;
            $realValues[$this->prefixKey($key)] = $value;
        }

        return $cachePool->setMultiple($realValues, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        $cachePool = $this->cachePool;

        $realKeys = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            $realKeys[] = $this->prefixKey($key);
        }

        return $cachePool->deleteMultiple($realKeys);
    }

    /**
     * @inheritDoc
     */
    public function has($key)
    {
        $cachePool = $this->cachePool;
        $realKey = $this->prefixKey($key);

        return $cachePool->has($realKey);
    }

    /**
     * Prefixes a cache key.
     *
     * @param string $key A key to prefix.
     *
     * @return string The prefixed key.
     */
    protected function prefixKey(string $key): string
    {
        $prefix = $this->prefix;
        $key = "{$prefix}{$key}";

        return $key;
    }

    /**
     * Removes a prefix from a key.
     *
     * @param string $key The key to remove the prefix from.
     *
     * @return string The key without the prefix.
     *
     * @throws RangeException If key does not start with prefix.
     * @throws RuntimeException If problem removing prefix.
     */
    protected function unprefixKey(string $key): string
    {
        $prefix = $this->prefix;
        $prefixLength = strlen($prefix);

        if (substr($key, 0, $prefixLength) !== $prefix) {
            throw new RangeException(
                sprintf(
                    'Key "%1$s" does not start with prefix "%2$s"',
                    $key,
                    $prefix
                )
            );
        }

        $result = substr($key, $prefixLength);
        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Could not extract real key from "%1$s", starting at char %2$s',
                    $key,
                    $prefixLength
                )
            );
        }

        return $result;
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
            /** @var list<string> $curKeys */
            foreach ($curKeys as $key) {
                yield $key;
            }
        }
    }
}
