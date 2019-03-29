<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue\Traits;

use Altair\Queue\Contracts\QueueConnectionInterface;

trait ConnectionInstanceAwareTrait
{
    /**
     * @var mixed
     */
    protected $instance;

    /**
     * @inheritdoc
     */
    public function getConnection(): QueueConnectionInterface
    {
        return $this->instance?? $this->connect()->getInstance();
    }

    /**
     * @inheritdoc
     */
    public function getInstance()
    {
        return $this->instance;
    }
}
