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
use DateTimeImmutable;

final class ProfileDto implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use SerializeAwareTrait;

    private ?int $id = null;

    private ?string $name = null;

    private ?bool $active = null;

    private ?float $score = null;

    private ?DateTimeImmutable $created_at = null;

    private ?AddressDto $address = null;
}
