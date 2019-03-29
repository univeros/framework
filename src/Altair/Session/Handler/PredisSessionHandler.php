<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Handler;

use Predis\Session\Handler;

class PredisSessionHandler extends Handler
{
    // Please, check parent class. Predis\Session\Handler requires Predis\ClientInterface::class
    // Make sure that interfaces is aliased with a proper configured Predis\Client::class instance.
}
