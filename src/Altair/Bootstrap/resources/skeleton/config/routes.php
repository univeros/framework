<?php

declare(strict_types=1);

use App\Http\Actions\PingAction;

/*
 * Route table: [METHOD, PATH, Action::class]. `bin/altair spec:scaffold`
 * appends an entry here for each endpoint you generate.
 */
return [
    ['GET', '/ping', PingAction::class],
];
