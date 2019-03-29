<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
    const ATTRIBUTE_TIMEOUT = 'altair:queue:timeout';
    const ATTRIBUTE_FAILURE = 'altair:queue:failure';
}
