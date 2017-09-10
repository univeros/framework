<?php
namespace Altair\Container\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class InjectionException extends Exception implements ContainerExceptionInterface
{
}
