<?php
namespace Altair\Cache\Exception;

use Psr\Cache\CacheException as CacheExceptionInterface;
use RuntimeException;

class CacheException extends RuntimeException implements CacheExceptionInterface
{
}
