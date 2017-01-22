<?php
namespace Altair\Session\Handler;

use Predis\Session\Handler;

class RedisSessionHandler extends Handler
{
    // Please, check parent class. Predis\Session\Handler requires Predis\ClientInterface::class
    // Make sure that interfaces is aliased with a proper configured Predis\Client::class instance.
}
