<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session;

use Altair\Security\Support\Salt;
use Altair\Session\Contracts\CsrfTokenInterface;
use Altair\Session\Contracts\SessionBlockInterface;

class CsrfToken implements CsrfTokenInterface
{
    /**
     * @var SessionBlockInterface
     */
    protected $sessionBlock;
    /**
     * @var Salt
     */
    protected $salt;

    /**
     * CsrfToken constructor.
     *
     * @param SessionBlockInterface $sessionBlock
     * @param Salt $salt
     */
    public function __construct(SessionBlockInterface $sessionBlock, Salt $salt)
    {
        $this->sessionBlock = $sessionBlock;
        $this->salt = $salt;
    }

    /**
     * @inheritdoc
     */
    public function isValid(string $value): bool
    {
        return hash_equals($this->getValue(), $value);
    }

    /**
     * @inheritdoc
     */
    public function getValue(): string
    {
        return $this->sessionBlock->get('value');
    }

    /**
     * @inheritdoc
     */
    public function generateValue()
    {
        $value = hash('sha512', $this->salt->generate());
        $this->sessionBlock->set('value', $value);

        return $this->getValue();
    }
}
