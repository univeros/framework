<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Persistence\Dto\Fixture;

use Altair\Data\Contracts\DataObjectInterface;
use Altair\Data\Traits\ImmutableAttributesAwareTrait;
use Altair\Data\Traits\JsonSerializableAwareTrait;
use Altair\Data\Traits\SerializeAwareTrait;

final class AddressDto implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use SerializeAwareTrait;

    private ?string $city = null;

    private ?string $zip = null;
}
