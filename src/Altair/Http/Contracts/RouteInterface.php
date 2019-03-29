<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

interface RouteInterface
{
    /**
     * @return DomainInterface
     */
    public function getDomain();
    /**
     * @return InputInterface
     */
    public function getInput();
    /**
     * @return ResponderInterface
     */
    public function getResponder();
}
