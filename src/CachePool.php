<?php

declare(strict_types=1);

namespace Dhii\RedisCache;

use Dhii\RedisCache\Exception\CacheException;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Redis;
use Psr\SimpleCache\CacheException as CacheExceptionInterface;

/**
 * A Redis cache pool.
 */
class CachePool implements CacheInterface
{
    protected Redis $redis;

    /**
     * @param Redis $redis A configured and open Redis connection.
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        $key = (string) $key;
        $redis = $this->redis;
        $value = $redis->get($key);

        if ($value === null) {
            $value = $default;
        }

        return $value;
    }

    /**
     * @inheritDoc
     *
     * @throws CacheExceptionInterface If problem persisting.
     */
    public function set($key, $value, $ttl = null)
    {
        $key = (string) $key;
        $redis = $this->redis;

        $options = [];
        if ($ttl !== null) {
            $options['EX'] = $ttl;
        }

        try {
            $redis->set($key, $value, $options);
        } catch (Exception $e) {
            throw new CacheException(
                sprintf(
                    'Could not set value of length %1$d with TTL %2$ds for key "%3$s"',
                    strlen((string) $value),
                    $ttl,
                    $key
                ),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     *
     * @throws CacheExceptionInterface If problem deleting.
     */
    public function delete($key)
    {
        $key = (string) $key;
        $redis = $this->redis;

        try {
            $redis->del($key);
        } catch (Exception $e) {
            throw new CacheException(sprintf('Could not delete key "%a$s"', $key), 0, $e);
        }
    }

    /**
     * @inheritDoc
     *
     * @throws CacheExceptionInterface If problem wiping.
     */
    public function clear()
    {
        $redis = $this->redis;

        try {
            $redis->flushDB();
        } catch (Exception $e) {
            throw new CacheException('Could clear database', 0, $e);
        }
    }

    /**
     * @inheritDoc
     *
     * @throws CacheExceptionInterface If problem obtaining.
     */
    public function getMultiple($keys, $default = null)
    {
        $keys = (array) $keys;

        try {
            $redis = $this->redis->multi();
            foreach ($keys as $key) {
                $redis->get($key);
            }
            $values = $redis->exec();
        } catch (Exception $e) {
            throw new CacheException(sprintf('Could get values for %1$d keys', count($keys)), 0, $e);
        }

        foreach ($values as $key => $value) {
            if ($value === null) {
                $values[$key] = $default;
            }
        }

        $result = array_combine($keys, $values);

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @throws CacheExceptionInterface If problem persisting.
     */
    public function setMultiple($values, $ttl = null)
    {
        $values = (array) $values;
        try {
            $redis = $this->redis->multi();
            foreach ($values as $key => $value) {
                $options = [];
                if ($ttl !== null) {
                    $options['EX'] = $ttl;
                }

                $redis->set($key, $value, $options);
            }

            $redis->exec();
        } catch (Exception $e) {
            throw new CacheException(
                sprintf('Could set values for %1$d keys with ttl %2$ds', count($values), $ttl),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     *
     * @throws CacheExceptionInterface If problem deleting.
     */
    public function deleteMultiple($keys)
    {
        $keys = (array) $keys;
        $redis = $this->redis;

        try {
            $redis->del($keys);
        } catch (Exception $e) {
            throw new CacheException(
                sprintf('Could delete %1$d keys', count($keys)),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     *
     * @throws CacheExceptionInterface If problem determining.
     */
    public function has($key)
    {
        $key = (string) $key;
        $redis = $this->redis;
        try {
            $result = $redis->exists($key);
        } catch (Exception $e) {
            throw new CacheException(
                sprintf('Could determine if key "%1$s" exists', $key),
                0,
                $e
            );
        }

        return (bool) $result;
    }
}
