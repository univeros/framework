<?php
namespace Altair\Http\Support;

use Altair\Http\Contracts\IdentityValidatorInterface;

class ArrayIdentityValidator implements IdentityValidatorInterface
{
    /**
     * @var array|null
     */
    protected $users;

    /**
     * ArrayIdentityValidator constructor.
     *
     * @param array|null $users
     */
    public function __construct(array $users = null)
    {
        $this->users = $users?? [];
    }

    /**
     * @inheritdoc
     */
    public function __invoke(array $arguments)
    {
        $user = $arguments['user'];
        $password = $arguments['password'];

        if (!isset($this->users[$user])) {
            return false;
        }

        if (preg_match('/^\$(2|2a|2y)\$\d{2}\$.*/', $password) && (strlen($password) >= 60)) {
            return password_verify($password, $this->users[$user]);
        }
        return $password === $this->users[$user];
    }
}
