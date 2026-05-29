<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Dto\Attribute;

use Altair\Data\Contracts\DataObjectInterface;
use Attribute;

/**
 * Declares the element type of an `array` Data-object property so the hydrator
 * can project a list of rows (a to-many relation) into a list of Data objects.
 *
 * PHP cannot express `array<DataObjectInterface>` natively, so a to-many read
 * model annotates its collection property:
 *
 *     #[CollectionOf(OrderDto::class)]
 *     private ?array $orders = null;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CollectionOf
{
    /**
     * @param class-string<DataObjectInterface> $type
     */
    public function __construct(public string $type) {}
}
