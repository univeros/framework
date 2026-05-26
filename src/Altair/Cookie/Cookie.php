<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie;

use Altair\Cookie\Contracts\CookieInterface;
use Stringable;

final readonly class Cookie extends AbstractCookie implements CookieInterface, Stringable
{
    public function __construct(string $name, ?string $value = null)
    {
        parent::__construct($name, $value);
    }

    #[\Override]
    public function __toString(): string
    {
        return urlencode($this->name) . '=' . urlencode($this->value ?? '');
    }

    public function withValue(?string $value): self
    {
        return new self($this->name, $value);
    }
}
