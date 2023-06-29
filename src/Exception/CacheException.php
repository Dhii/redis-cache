<?php

declare(strict_types=1);

namespace Dhii\RedisCache\Exception;

use Exception;
use Psr\SimpleCache\CacheException as CacheExceptionInterface;

/**
 * Represents a problem with cache.
 */
class CacheException extends Exception implements CacheExceptionInterface
{
}
