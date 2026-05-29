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
use Altair\Http\Validator\DigestSignatureValidator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DigestSignatureValidator::class)]
final class DigestSignatureValidatorTest extends TestCase
{
    private const string REALM = 'Restricted';

    private const string METHOD = 'GET';

    private const string URI = '/secret';

    public function testReturnsTrueForValidDigestResponse(): void
    {
        $validator = new DigestSignatureValidator($this->providerWith([
            ['username' => 'alice', 'password' => 'secret'],
        ]));

        $authorization = $this->authorizationFor('alice', 'secret');

        $this->assertTrue($validator($this->argumentsFor($authorization)));
    }

    public function testReturnsFalseForTamperedResponse(): void
    {
        $validator = new DigestSignatureValidator($this->providerWith([
            ['username' => 'alice', 'password' => 'secret'],
        ]));

        $authorization = $this->authorizationFor('alice', 'secret');
        $authorization['response'] = strrev($authorization['response']);

        $this->assertFalse($validator($this->argumentsFor($authorization)));
    }

    public function testReturnsFalseWhenIdentityNotFound(): void
    {
        $validator = new DigestSignatureValidator($this->providerWith([]));

        $authorization = $this->authorizationFor('ghost', 'secret');

        $this->assertFalse($validator($this->argumentsFor($authorization)));
    }

    /**
     * @return array<string, string>
     */
    private function authorizationFor(string $username, string $password): array
    {
        $nonce = 'abc123';
        $nc = '00000001';
        $cnonce = 'xyz789';
        $qop = 'auth';

        $response = md5(implode(':', [
            md5(\sprintf('%s:%s:%s', $username, self::REALM, $password)),
            $nonce,
            $nc,
            $cnonce,
            $qop,
            md5(\sprintf('%s:%s', self::METHOD, self::URI)),
        ]));

        return [
            'username' => $username,
            'nonce' => $nonce,
            'nc' => $nc,
            'cnonce' => $cnonce,
            'qop' => $qop,
            'uri' => self::URI,
            'response' => $response,
        ];
    }

    /**
     * @param array<string, string> $authorization
     *
     * @return array<string, mixed>
     */
    private function argumentsFor(array $authorization): array
    {
        return [
            'authorization' => $authorization,
            'realm' => self::REALM,
            'method' => self::METHOD,
        ];
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
