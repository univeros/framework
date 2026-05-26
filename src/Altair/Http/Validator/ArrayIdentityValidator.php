<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Validator;

use Altair\Http\Contracts\IdentityValidatorInterface;

class ArrayIdentityValidator implements IdentityValidatorInterface
{
    protected array $users;

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
     * @inheritDoc
     */
    #[\Override]
    public function __invoke(array $arguments): bool
    {
        $user = $arguments['user'];
        $password = $arguments['password'];

        if (!isset($this->users[$user])) {
            return false;
        }

        if (preg_match('/^\$(2|2a|2y)\$\d{2}\$.*/', (string) $password) && (strlen((string) $password) >= 60)) {
            return password_verify((string) $password, (string) $this->users[$user]);
        }

        return $password === $this->users[$user];
    }
}
