<?php
namespace Altair\Session\Handler;

use MongoBinData;
use MongoCollection;
use MongoDate;
use SessionHandlerInterface;

class MongoSessionHandler implements SessionHandlerInterface
{
    /**
     * Session collection
     * @var MongoCollection
     */
    protected $collection;

    /**
     * Class constructor
     *
     * @param MongoCollection $collection
     */
    public function __construct(MongoCollection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * @inheritdoc
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function read($sessionId)
    {
        $data = $this->collection->findOne(['_id' => $sessionId]);

        return null === $data || !isset($data['session_data'])
            ? ''
            : $data['session_data']->bin;
    }

    /**
     * @inheritdoc
     */
    public function write($sessionId, $data)
    {
        $this->collection->update(
            [
                '_id' => $sessionId
            ],
            [
                '$set' => [
                    'session_data' => new MongoBinData($data, MongoBinData::BYTE_ARRAY),
                    'timestamp' => new MongoDate(),
                ]
            ],
            [
                'upsert' => true,
                'multiple' => false
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function destroy($sessionId)
    {
        $this->collection->remove(['_id' => $sessionId]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function gc($maxlifetime)
    {
        $time = new MongoDate(time() - $maxlifetime);
        $this->collection->remove(
            [
                'timestamp' => ['$l' => $time]
            ]
        );

        return true;
    }
}
