<?php

namespace Altair\Queue\Contracts;

interface JobInterface
{
    const ATTRIBUTE_ID = 'altair:queue:id';
    const ATTRIBUTE_DATA = 'altair:queue:data';
    const ATTRIBUTE_STATUS = 'altair:queue:status';
    const ATTRIBUTE_DELAY = 'altair:queue:delay';
    const ATTRIBUTE_JOB = 'altair:queue:job';
    const ATTRIBUTE_QUEUE_NAME = 'altair:queue:name';
    const ATTRIBUTE_COMPLETED = 'altair:queue:completed';
}
