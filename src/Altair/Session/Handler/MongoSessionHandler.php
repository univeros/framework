<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Handler;

use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception as MongoDBException;
use Override;
use ReturnTypeWillChange;
use SessionHandlerInterface;

class MongoSessionHandler implements SessionHandlerInterface
{
    /**
     * Class constructor
     */
    public function __construct(
        /**
         * Session collection
         */
        protected Collection $collection
    ) {}

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function close()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function read($sessionId)
    {
        $data = $this->collection->findOne(
            ['_id' => $sessionId, 'session_lifetime' => ['$gte' => $this->createUTCDateTime()]],
            ['typeMap' => ['root' => 'array', 'document' => 'array']]
        );

        if (!\is_array($data) || !isset($data['content'])) {
            return '';
        }

        $content = $data['content'];

        return $content instanceof Binary ? $content->getData() : '';
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function write($sessionId, $data)
    {
        try {
            $expires = $this->createUTCDateTime(time() + (int) \ini_get('session.gc_maxlifetime'));

            $this->collection->updateOne(
                [
                    '_id' => $sessionId,
                ],
                [
                    '$set' => [
                        'content' => new Binary($data, Binary::TYPE_OLD_BINARY),
                        'session_lifetime' => $expires,
                        'session_time' => $this->createUTCDateTime(),
                    ],
                ],
                [
                    'upsert' => true,
                ]
            );
        } catch (MongoDBException) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function destroy($sessionId)
    {
        try {
            $this->collection->deleteOne(['_id' => $sessionId]);
        } catch (MongoDBException) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function gc($maxlifetime): int|false
    {
        try {
            $result = $this->collection->deleteMany(
                [
                    'session_lifetime' => ['$lt' => $this->createUTCDateTime()],
                ]
            );
        } catch (MongoDBException) {
            return false;
        }

        return $result->getDeletedCount();
    }

    /**
     * @param null|int $seconds
     */
    private function createUTCDateTime($seconds = null): UTCDateTime
    {
        if (null === $seconds) {
            $seconds = time();
        }

        return new UTCDateTime($seconds * 1000);
    }
}
