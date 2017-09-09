<?php

namespace Altair\Session\Handler;

use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception as MongoDBException;
use SessionHandlerInterface;

class MongoSessionHandler implements SessionHandlerInterface
{
    /**
     * Session collection
     * @var Collection
     */
    protected $collection;

    /**
     * Class constructor
     *
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
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
        $data = $this->collection->findOne(
            ['_id' => $sessionId, 'session_lifetime' => ['$gte' => $this->createUTCDateTime()]]
        );

        return null === $data || !isset($data['content'])
            ? ''
            : $data['content']->getData();
    }

    /**
     * @inheritdoc
     */
    public function write($sessionId, $data)
    {
        try {
            $expires = $this->createUTCDateTime(time() + (int) ini_get('session.gc_maxlifetime'));

            $this->collection->updateOne(
                [
                    '_id' => $sessionId
                ],
                [
                    '$set' => [
                        'content' => new Binary($data, Binary::TYPE_OLD_BINARY),
                        'session_lifetime' => $expires,
                        'session_time' => $this->createUTCDateTime()
                    ]
                ],
                [
                    'upsert' => true,
                    'multiple' => false
                ]
            );
        } catch (MongoDBException $e) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function destroy($sessionId)
    {
        try {
            $this->collection->deleteOne(['_id' => $sessionId]);
        } catch (MongoDBException  $e) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function gc($maxlifetime)
    {
        try {
            $this->collection->deleteMany(
                [
                    'session_lifetime' => ['$lt' => $this->createUTCDateTime()]
                ]
            );
        } catch (MongoDBException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param null|int $seconds
     *
     * @return UTCDateTime
     */
    private function createUTCDateTime($seconds = null)
    {
        if (null === $seconds) {
            $seconds = time();
        }

        return new UTCDateTime($seconds * 1000);
    }
}
