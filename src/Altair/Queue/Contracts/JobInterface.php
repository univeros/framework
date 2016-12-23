<?php

namespace Altair\Queue\Contracts;

interface JobInterface
{
    public function delete();

    public function complete();

    public function failed();

    public function release($delay = 0);

    public function getAttempts():int;

    public function getPayload();
}
