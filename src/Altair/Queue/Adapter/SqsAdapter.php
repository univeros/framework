<?php
namespace Altair\Queue\Adapter;

use Altair\Cache\Exception\InvalidMethodCallException;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Middleware\Payload;
use Altair\Queue\Connection\SqsConnection;
use Altair\Queue\Contracts\JobInterface;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Traits\EnsureIdAwareTrait;
use Aws\Result;

class SqsAdapter extends AbstractAdapter
{
    use EnsureIdAwareTrait;

    /**
     * SqsAdapter constructor.
     *
     * @param SqsConnection $connection
     */
    public function __construct(SqsConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @inheritdoc
     */
    public function push(PayloadInterface $payload): bool
    {
        $data = $payload->getAttribute(JobInterface::ATTRIBUTE_DATA, []);

        $args = array_filter(
            array_merge(
                $data,
                [
                    'QueueUrl' => $this->getQueueUrlFromPayload($payload),
                ]
            )
        );

        $id = $this->getConnection()->getInstance()->sendMessage($args)->get('MessageId');

        return null !== $id && is_string($id);
    }

    /**
     * @inheritdoc
     */
    public function pop(string $queue = null): ?PayloadInterface
    {
        $queue = $queue?? QueueAdapterInterface::DEFAULT_QUEUE_NAME;

        $url = $this->getQueueUrl(['QueueName' => $queue]);

        /** @var \Aws\Result $result */
        $result = $this->getConnection()->getInstance()->receiveMessage(['QueueUrl' => $url]);

        if ($result->search('Messages') !== null) {
            $data = [
                JobInterface::ATTRIBUTE_ID => $result['MessageId'],
                JobInterface::ATTRIBUTE_DATA => [
                    'ReceiptHandle' => $result['ReceiptHandle'],
                    'MessageBody' => $result['Body'],
                    'Attempt' => $result['Attempt'],
                ]
            ];

            return (new Payload($data))
                ->withAttribute(JobInterface::ATTRIBUTE_QUEUE_NAME, $queue)
                ->withAttribute(JobInterface::ATTRIBUTE_JOB, $result);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function ack(PayloadInterface $payload)
    {
        if (!$this->hasIdAttribute($payload)) {
            throw new InvalidMethodCallException('Job must have an id to be updated on queue.');
        }
        if (!($job = $payload->getAttribute(JobInterface::ATTRIBUTE_JOB)) instanceof Result) {
            throw new InvalidMethodCallException('Payload does not have a valid SqS job.');
        }
        $url = $this->getQueueNameFromAttribute($payload);

        if ($payload->getAttribute(JobInterface::ATTRIBUTE_COMPLETED) === true) {
            $this->getConnection()->getInstance()->deleteMessage(
                [
                    'QueueUrl' => $url,
                    'ReceiptHandle' => $job['ReceiptHandle']
                ]
            );
        } else {
            $delay = $payload->getAttribute(JobInterface::ATTRIBUTE_DELAY, 0);

            $this->getConnection()->getInstance()->changeMessageVisibility(
                [
                    'QueueUrl' => $url,
                    'ReceiptHandle' => $job['ReceiptHandle'],
                    'VisibilityTimeout' => $delay
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function isEmpty(string $queue = null): bool
    {
        $url = $this->getQueueUrl(['QueueName' => $queue?? QueueAdapterInterface::DEFAULT_QUEUE_NAME]);

        /** @var \Aws\Result $attributes */
        $attributes = $this->getConnection()->getInstance()->getQueueAttributes(
            [
                'QueueUrl' => $url,
                'AttributeNames' => ['ApproximateNumberOfMessages'],
            ]
        );

        return $attributes->search('Attributes.ApproximateNumberOfMessages') === 0;
    }

    /**
     * @param PayloadInterface $payload
     *
     * @return string
     */
    protected function getQueueUrlFromPayload(PayloadInterface $payload): string
    {
        $name = $this->getQueueNameFromAttribute($payload);
        $attributes = $payload->getAttribute($name . ':attributes', null);
        $config = array_filter(
            [
                'QueueName' => $name,
                'Attributes' => $attributes
            ]
        );

        return $this->getQueueUrl($config);
    }

    /**
     * @param array $config
     *
     * @return string
     */
    protected function getQueueUrl(
        array $config
    ): string {
        // @see http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#createqueue
        return $this->getConnection()->getInstance()->createQueue($config)->get('QueueUrl');
    }
}
