<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Validator;

use Altair\Http\Contracts\IdentityProviderInterface;
use Altair\Http\Validator\RepositoryIdentityValidator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RepositoryIdentityValidator::class)]
final class RepositoryIdentityValidatorTest extends TestCase
{
    public function testReturnsTrueForMatchingPassword(): void
    {
        $provider = $this->providerWith([
            ['username' => 'alice', 'hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $validator = new RepositoryIdentityValidator($provider);

        $this->assertTrue($validator(['user' => 'alice', 'password' => 'secret']));
    }

    public function testReturnsFalseForWrongPassword(): void
    {
        $provider = $this->providerWith([
            ['username' => 'alice', 'hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $validator = new RepositoryIdentityValidator($provider);

        $this->assertFalse($validator(['user' => 'alice', 'password' => 'wrong']));
    }

    public function testReturnsFalseWhenIdentityNotFound(): void
    {
        $validator = new RepositoryIdentityValidator($this->providerWith([]));

        $this->assertFalse($validator(['user' => 'ghost', 'password' => 'secret']));
    }

    public function testHonoursCustomFieldOptions(): void
    {
        $provider = $this->providerWith([
            ['login' => 'alice', 'pwd' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $validator = new RepositoryIdentityValidator($provider, ['username' => 'login', 'hash' => 'pwd']);

        $this->assertTrue($validator(['user' => 'alice', 'password' => 'secret']));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function providerWith(array $rows): IdentityProviderInterface
    {
        return new readonly class($rows) implements IdentityProviderInterface {
            /**
             * @param list<array<string, mixed>> $rows
             */
            public function __construct(private array $rows)
            {
            }

            #[Override]
            public function findOneBy(array $criteria): ?array
            {
                foreach ($this->rows as $row) {
                    if (array_intersect_assoc($criteria, $row) === $criteria) {
                        return $row;
                    }
                }

                return null;
            }
        };
    }
}
