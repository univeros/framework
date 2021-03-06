<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

use Altair\Http\Collection\InputCollection;

interface DomainInterface
{
    /**
     * Handle domain logic for an action.
     *
     * @param InputCollection $input
     *
     * @return PayloadInterface
     */
    public function __invoke(InputCollection $input): PayloadInterface;
}
