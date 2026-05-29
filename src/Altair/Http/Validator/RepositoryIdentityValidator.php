<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Validator;

use Altair\Http\Contracts\IdentityProviderInterface;
use Altair\Http\Contracts\IdentityValidatorInterface;
use Override;

class RepositoryIdentityValidator implements IdentityValidatorInterface
{
    /**
     * @var array<string, string>
     */
    protected array $options;

    /**
     * RepositoryIdentityValidator constructor.
     *
     * @param array<string, string>|null $options
     */
    public function __construct(protected IdentityProviderInterface $repository, ?array $options = null)
    {
        // Options contain the names of the fields to search on the db by the entity.
        // By default: 'username' and 'hash' are the default fieldname values. You can easily change them as:
        // ['username' => 'my_field_username', 'hash' => 'my_field_name_for_hash']

        $this->options = $options ?? ['username' => 'username', 'hash' => 'hash'];
    }

    /**
     * @inheritDoc
     * @param array<string, mixed> $arguments
     */
    #[Override]
    public function __invoke(array $arguments): bool
    {
        $user = $arguments["user"] ?? null;
        $password = $arguments["password"] ?? null;

        $identity = $this->repository->findOneBy([$this->options['username'] => $user]);

        if ($identity !== null) {
            return password_verify((string) $password, (string) ($identity[$this->options['hash']] ?? ''));
        }

        return false;
    }
}
