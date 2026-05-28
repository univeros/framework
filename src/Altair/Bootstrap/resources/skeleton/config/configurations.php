<?php

declare(strict_types=1);

/*
 * The Configuration chain applied to the container at boot. The minimal
 * skeleton needs none — the container autowires your Actions, Domains and
 * Responders. `bin/altair new` adds entries here when you choose an ORM
 * (Cycle) or a queue (Messenger): return the relevant Configuration instances.
 *
 * Example:
 *   return [
 *       new Altair\Persistence\Configuration\CycleOrmConfiguration(),
 *       new Altair\Messaging\Configuration\MessengerConfiguration(),
 *   ];
 *
 * @return list<Altair\Configuration\Contracts\ConfigurationInterface>
 */
return [];
