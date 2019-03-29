<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue\Connection;

use Altair\Queue\Contracts\QueueConnectionInterface;
use Altair\Queue\Traits\ConnectionInstanceAwareTrait;
use Aws\Sqs\SqsClient;

class SqsConnection implements QueueConnectionInterface
{
    use ConnectionInstanceAwareTrait;

    protected $key;
    protected $secret;
    protected $region;

    /**
     * SqsConnection constructor.
     *
     * @param string $key
     * @param string $secret
     * @param string|null $region
     */
    public function __construct(string $key, string $secret, string $region = null)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->region = $region;
    }

    /**
     * @inheritdoc
     */
    public function connect(): QueueConnectionInterface
    {
        if (null === $this->instance) {
            $this->instance = new SqsClient(
                [
                    'key' => $this->key,
                    'secret' => $this->secret,
                    'region' => $this->region
                ]
            );
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function disconnect(): bool
    {
        $this->instance = null;
    }
}
