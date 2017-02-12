<?php
namespace Altair\Queue\Adapter;

use Altair\Cache\Exception\InvalidMethodCallException;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Middleware\Payload;
use Altair\Queue\Connection\PdoConnection;
use Altair\Queue\Contracts\JobInterface;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Traits\EnsureIdAwareTrait;
use PDO;

class PdoAdapter extends AbstractAdapter
{
    use EnsureIdAwareTrait;

    /**
     * PdoAdapter constructor.
     *
     * @param PdoConnection $connection
     */
    public function __construct(PdoConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @inheritdoc
     */
    public function push(PayloadInterface $payload): bool
    {
        $queue = $this->getQueueNameFromAttribute($payload);

        $sql = sprintf(
            'INSERT INTO `%s` (`message`, `tts`) VALUES (:message, :tts)',
            $queue
        );

        /** @var \PDOStatement $query */
        $query = $this->getConnection()->getInstance()->prepare($sql);
        $query->bindValue(':message', json_encode($payload->getAttribute(JobInterface::ATTRIBUTE_DATA)));
        $query->bindValue(':tts', $payload->getAttribute(JobInterface::ATTRIBUTE_DELAY));

        return $query->execute();
    }

    /**
     * @inheritdoc
     */
    public function pop(string $queue = null): ?PayloadInterface
    {
        $queue = $queue?? QueueAdapterInterface::DEFAULT_QUEUE_NAME;

        $sql = 'SELECT `id`, `message`
            FROM `%s` WHERE `tts` <= NOW()
            ORDER BY id ASC LIMIT 1';

        $sql = sprintf($sql, $queue);
        /** @var \PDOStatement $query */
        $query = $this->getConnection()->getInstance()->prepare($sql);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        $payload = null;

        if ($result) {
            $sql = sprintf('DELETE * FROM `%s` WHERE `id`=:id', $result['id']);
            $query = $this->getConnection()->getInstance()->prepare($sql);
            $query->bindValue(':id', $result['id'], PDO::PARAM_INT);
            $query->execute();

            $payload = new Payload(
                [
                    JobInterface::ATTRIBUTE_ID => $result['id'],
                    JobInterface::ATTRIBUTE_DATA => json_decode($result['message'], true),
                    JobInterface::ATTRIBUTE_QUEUE_NAME => $queue,
                    JobInterface::ATTRIBUTE_JOB => $result
                ]
            );
        }

        return $payload;
    }

    /**
     * @inheritdoc
     */
    public function ack(PayloadInterface $payload)
    {
        if (!$this->hasIdAttribute($payload)) {
            throw new InvalidMethodCallException('Job must have an id to be updated. Otherwise, use "push()" method.');
        }

        if ($payload->getAttribute(JobInterface::ATTRIBUTE_COMPLETED) !== true) {
            $this->push($payload->withoutAttribute(JobInterface::ATTRIBUTE_ID));
        }
    }

    /**
     * @inheritdoc
     */
    public function isEmpty(string $queue = null): bool
    {
        $sql = sprintf(
            'SELECT COUNT(`id`) FROM `%s` WHERE `tts` <= NOW() ORDER BY id ASC LIMIT 1',
            $queue?? QueueAdapterInterface::DEFAULT_QUEUE_NAME
        );
        /** @var \PDOStatement $query */
        $query = $this->getConnection()->getInstance()->prepare($sql);
        $query->execute();

        return intval($query->fetchColumn(0)) === 0;
    }
}
