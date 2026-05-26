<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Validator;

use Altair\Data\Contracts\QueryRepositoryInterface;
use Altair\Http\Contracts\IdentityValidatorInterface;
use Override;

class DigestSignatureValidator implements IdentityValidatorInterface
{
    protected array $options;

    /**
     * RepositoryIdentityValidator constructor.
     *
     * @param array|null $options
     */
    public function __construct(protected QueryRepositoryInterface $repository, array $options = null)
    {
        // Options contain the names of the fields to search on the db by the entity.
        // By default: 'username' and 'hash' are the default fieldname values. You can easily change them as:
        // ['username' => 'my_field_username', 'password' => 'my_field_name_for_password']
        $this->options = $options ?? ['username' => 'username', 'password' => 'password'];
    }

    #[Override]
    public function __invoke(array $arguments): bool
    {
        $authorization = $arguments['authorization'];
        $realm = $arguments['realm'];
        $method = $arguments['method'];
        $user = $this->repository->findOneBy([$this->options['username'] => $authorization['username']]);
        $data = [
            md5(\sprintf('%s:%s:%s', $authorization['username'], $realm, $user->get($this->options['password']))),
            $authorization['nonce'],
            $authorization['nc'],
            $authorization['cnonce'],
            $authorization['qop'],
            md5(\sprintf('%s:%s', $method, $authorization['uri'])),
        ];
        $hash = md5(implode(':', $data));
        return $authorization['response'] === $hash;
    }
}
