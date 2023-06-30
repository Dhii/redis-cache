# Dhii - Redis Cache
[![Continuous Integration](https://github.com/dhii/redis-cache/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/dhii/redis-cache/actions/workflows/continuous-integration.yml)

A [PSR-16][]-compliant [Redis][] cache wrapper.

## Requirements
- PHP 7.4 - 8.2;
- Redis >= 2.6.12.

## Details
Use a factory to easily create cache pools.

```php
use Dhii\RedisCache\CachePoolFactory;
/** @var Redis $redis */

$factory = new CachePoolFactory($redis);
$cache = $factory->createCachePool();
$cache->has('mykey');
$cache->clear(); // Empties DB
```

A common approach is to prefix keys with a delimited namespace.
These will become logically isolated, and can be cleared all at once.

```php
// .. continued from previous example
use Dhii\RedisCache\CachePool;
use Dhii\RedisCache\KeyPrefixingCachePoolFactory;
/** @var Redis $redis */
/** @var CachePool $cache */

$namespace = 'mystuff';
$prefix = "{$namespace}:";

/*
 * It's important to use the same `Redis` instance
 * for the prefixing factory as for the base one,
 * because they SHOULD operate on the same database.
 * This is relevant specifically for `clear()`, as
 * the prefixing factory has to find keys by prefix,
 * whereby base cache will empty the whole DB.
 */
$prefixingFactory = new KeyPrefixingCachePoolFactory($cache, $redis);
$prefixingCache = $prefixingFactory->createCachePoolWithPrefix($prefix);
$prefixingCache->has('mykey'); // Actually checks for `mystuff:mykey`
$prefixingCache->clear(); // Removes keys that match `mystuff:*` only
```

## Notes
Started with [`dhii/php-project`][].


[PSR-16]: https://www.php-fig.org/psr/psr-16/
[Redis]: https://redis.io/
[`dhii/php-project`]: https://github.com/Dhii/php-project
