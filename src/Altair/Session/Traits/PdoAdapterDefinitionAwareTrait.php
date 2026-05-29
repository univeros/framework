<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Traits;

use Altair\Session\Contracts\PdoSessionAdapterInterface;

trait PdoAdapterDefinitionAwareTrait
{
    /**
     * @return array<string, mixed>
     */
    protected function getAdapterParameters(): array
    {
        return [
            'dsn' => $this->env->get('SESSION_PDO_DSN'),
            'username' => $this->env->get('SESSION_PDO_USERNAME'),
            'password' => $this->env->get('SESSION_PDO_PASSWORD'),
            'table' => $this->env->get('SESSION_PDO_TABLE'),
            'lockMode' => (int) $this->env->get(
                'SESSION_LOCK_MODE',
                PdoSessionAdapterInterface::LOCK_TRANSACTIONAL
            ),
            'options' => [], // if you need to add X connection options, create your own ;)
        ];
    }
}
