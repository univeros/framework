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
     * CsrfToken constructor.
     */
    public function __construct(protected SessionBlockInterface $sessionBlock, protected Salt $salt)
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function isValid(string $value): bool
    {
        return hash_equals($this->getValue(), $value);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getValue(): string
    {
        return $this->sessionBlock->get('value');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function generateValue(): string
    {
        $value = hash('sha512', $this->salt->generate());
        $this->sessionBlock->set('value', $value);

        return $this->getValue();
    }
}
