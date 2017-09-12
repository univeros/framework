<?php
namespace Altair\Http\Validator;

use Altair\Data\Contracts\QueryRepositoryInterface;
use Altair\Http\Contracts\IdentityValidatorInterface;

class DigestSignatureValidator implements IdentityValidatorInterface
{
    /**
     * @var QueryRepositoryInterface
     */
    protected $repository;
    /**
     * @var array|null
     */
    protected $options;

    /**
     * RepositoryIdentityValidator constructor.
     *
     * @param QueryRepositoryInterface $repository
     * @param array|null $options
     */
    public function __construct(QueryRepositoryInterface $repository, array $options = null)
    {
        $this->repository = $repository;

        // Options contain the names of the fields to search on the db by the entity.
        // By default: 'username' and 'hash' are the default fieldname values. You can easily change them as:
        // ['username' => 'my_field_username', 'password' => 'my_field_name_for_password']
        $this->options = $options?? ['username' => 'username', 'password' => 'password'];
    }

    /**
     * @param array $arguments
     *
     * @return bool
     */
    public function __invoke(array $arguments): bool
    {
        $authorization = $arguments['authorization'];
        $realm = $arguments['realm'];
        $method = $arguments['method'];
        $user = $this->repository->findOneBy([$this->options['username'] => $authorization['username']]);
        if (!null !== $user) {
            $data = [
                md5("{$authorization['username']}:{$realm}:{$user->get($this->options['password'])}"),
                $authorization['nonce'],
                $authorization['nc'],
                $authorization['cnonce'],
                $authorization['qop'],
                md5("{$method}:{$authorization['uri']}")
            ];
            $hash = md5(implode(':', $data));

            return $authorization['response'] === $hash;
        }

        return false;
    }
}
