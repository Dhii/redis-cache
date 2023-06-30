<?php

declare(strict_types=1);

namespace Dhii\RedisCache;

use DateInterval;
use DateTime;
use Dhii\RedisCache\Exception\CacheException;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Redis;
use Traversable;

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
     * @throws CacheException If problem persisting.
     */
    public function set($key, $value, $ttl = null)
    {
        $value = (string) $value;
        $redis = $this->redis;
        $ttl = $ttl instanceof DateInterval
            ? $this->getIntervalSeconds($ttl)
            : $ttl;

        $options = [];
        if ($ttl !== null) {
            $options['EX'] = $ttl;
        }

        try {
            return (bool) $redis->set($key, $value, $options);
        } catch (Exception $e) {
            throw new CacheException(
                sprintf(
                    'Could not set value of length %1$d with TTL %2$ds for key "%3$s"',
                    strlen($value),
                    $ttl ?: 'null',
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
     * @throws CacheException If problem deleting.
     */
    public function delete($key)
    {
        $redis = $this->redis;

        try {
            return (bool) $redis->del($key);
        } catch (Exception $e) {
            throw new CacheException(sprintf('Could not delete key "%1$s"', $key), 0, $e);
        }
    }

    /**
     * @inheritDoc
     *
     * @throws CacheException If problem wiping.
     */
    public function clear()
    {
        $redis = $this->redis;

        try {
            return $redis->flushDB();
        } catch (Exception $e) {
            throw new CacheException('Could clear database', 0, $e);
        }
    }

    /**
     * @inheritDoc
     *
     * @throws CacheException If problem obtaining.
     */
    public function getMultiple($keys, $default = null)
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys);
        }

        try {
            /** @var Redis $redis */
            $redis = $this->redis->multi();
            foreach ($keys as $key) {
                $key = (string) $key;
                $redis->get($key);
            }
            /** @var list<string> $values */
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
        if ($result === false) {
            throw new CacheException('Could not combine retrieved values with specified keys');
        }

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @throws CacheException If problem persisting.
     */
    public function setMultiple($values, $ttl = null)
    {
        $values = (array) $values;
        $ttl = $ttl instanceof DateInterval
            ? $this->getIntervalSeconds($ttl)
            : $ttl;
        try {
            /** @var Redis $redis */
            $redis = $this->redis->multi();
            foreach ($values as $key => $value) {
                $value = (string) $value;
                $options = [];
                if ($ttl !== null) {
                    $options['EX'] = $ttl;
                }

                $redis->set($key, $value, $options);
            }

            return (bool) $redis->exec();
        } catch (Exception $e) {
            throw new CacheException(
                sprintf('Could set values for %1$d keys with ttl %2$s', count($values), $ttl ? "${ttl}s" : 'null'),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     *
     * @throws CacheException If problem deleting.
     */
    public function deleteMultiple($keys)
    {
        $keys = (array) $keys;
        $redis = $this->redis;

        try {
            return (bool) $redis->del($keys);
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
     * @throws CacheException If problem determining.
     */
    public function has($key)
    {
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

    /**
     * Retrieves the number of seconds in an interval.
     *
     * @param DateInterval $interval The interval.
     * @return int The number of seconds.
     */
    protected function getIntervalSeconds(DateInterval $interval): int
    {
        return (int) DateTime::createFromFormat('U', '0') // A 0 date
            ->add($interval) // Add the interval
            ->format('U'); // Get new date with interval time from 0;
    }
}
