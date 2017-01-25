<?php

namespace Altair\Queue\Contracts;

interface JobInterface
{
    public function delete(): bool;

    public function complete(): bool;

    public function failed(): bool;

    public function release($delay = 0);

    public function getId(): ?int;

    public function getTimeToSend(): int;

    public function getAttempts(): int;

    public function getMaxAttempts(): int;

    public function getData();
}
