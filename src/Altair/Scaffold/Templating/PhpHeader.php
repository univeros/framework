<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Templating;

/**
 * Single source of truth for the standard file header used at the top of
 * every emitted PHP file.
 */
final class PhpHeader
{
    public static function render(string $namespace): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            /*
             * This file is part of the univeros/framework
             *
             * For the full copyright and license information, please view
             * the LICENSE file that was distributed with this source code.
             */

            namespace {$namespace};


            PHP;
    }
}
