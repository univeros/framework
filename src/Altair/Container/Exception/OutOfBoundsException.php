<?php
namespace Altair\Container\Exception;

use Psr\Container\ContainerExceptionInterface;

class OutOfBoundsException extends \OutOfBoundsException implements ContainerExceptionInterface
{
}
