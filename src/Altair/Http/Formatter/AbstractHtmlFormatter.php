<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Formatter;

use Altair\Http\Contracts\OutputFormatterInterface;

abstract class AbstractHtmlFormatter implements OutputFormatterInterface
{
    /**
     * @return array
     */
    public static function accepts(): array
    {
        return ['text/html'];
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return 'text/html';
    }
}
