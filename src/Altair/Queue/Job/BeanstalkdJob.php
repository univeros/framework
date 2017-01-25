<?php
namespace Altair\Queue\Job;

use Altair\Queue\Contracts\JobInterface;

class BeanstalkdJob implements JobInterface
{
    public function delete(): bool
    {
        // TODO: Implement delete() method.
    }

    public function complete(): bool
    {
        // TODO: Implement complete() method.
    }

    public function failed(): bool
    {
        // TODO: Implement failed() method.
    }

    public function release($delay = 0)
    {
        // TODO: Implement release() method.
    }

    public function isDeleted(): bool {

    }

    public function isCompleted(): bool {

    }

    public function getId(): ?int
    {
        // TODO: Implement getId() method.
    }

    public function getTimeToSend(): int
    {
        // TODO: Implement getTimeToSend() method.
    }

    public function getAttempts(): int
    {
        // TODO: Implement getAttempts() method.
    }

    public function getMaxAttempts(): int
    {
        // TODO: Implement getMaxAttempts() method.
    }

    public function getData()
    {
        // TODO: Implement getData() method.
    }

}
