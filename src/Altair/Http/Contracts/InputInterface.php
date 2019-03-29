<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

use Altair\Http\Collection\InputCollection;
use Psr\Http\Message\ServerRequestInterface;

interface InputInterface
{
    /**
     * Extract domain input from the request.
     *
     * @param  ServerRequestInterface $request
     *
     * @return InputCollection
     */
    public function __invoke(ServerRequestInterface $request): InputCollection;
}
