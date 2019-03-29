<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Contracts;

use Altair\Sanitation\Collection\FilterCollection;

interface SanitizableInterface
{
    /**
     * Returns the rules of the object to be used to validate against x input. The format for the rules follows the
     * next syntax:
     *
     * ```
     *  return new RulesCollection([
     *      'keyA' => [ // will be used to validate input values against the rules that hold the `key`
     *          RuleB::class,
     *          ['class' => RuleC::class, ':argument1' => 'value1']
     *      ],
     *      'keyB,keyC' => [ // we could have multiple keys, they'll get normalized
     *         ['class' => RuleB::class, ':argument1' => 'value1', ':argument2' => 'value2']
     *      ],
     *      'keyC' => RuleD::class
     * ]);
     *
     * @return FilterCollection
     */
    public function getFilters(): FilterCollection;
}
