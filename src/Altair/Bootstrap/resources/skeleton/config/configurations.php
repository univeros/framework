<?php

declare(strict_types=1);

/*
 * The Configuration chain applied to the container at boot. Logging is wired
 * first so every package that asks for a PSR-3 `LoggerInterface` (the HTTP
 * error handler, the event recorder, the worker, ...) gets a real logger
 * instead of a NullLogger. `bin/altair new` adds entries here when you choose
 * an ORM (Cycle) or a queue (Messenger): return the relevant Configuration
 * instances.
 *
 * Example:
 *   return [
 *       new Altair\Logging\Configuration\LoggingConfiguration(),
 *       new Altair\Persistence\Configuration\CycleOrmConfiguration(),
 *       new Altair\Messaging\Configuration\MessengerConfiguration(),
 *   ];
 *
 * @return list<Altair\Configuration\Contracts\ConfigurationInterface>
 */
return [
    new Altair\Logging\Configuration\LoggingConfiguration(),
];
