# vendor/module

A pluggable [Univeros](https://univeros.io) module. Once registered in a host
app it contributes its HTTP routes, Cycle entities, and migrations with no
further host wiring.

## Install

```bash
composer require vendor/module
```

Then register the module in your host app's `config/modules.php`:

```php
<?php

declare(strict_types=1);

return [
    new VendorModule\Module(),
];
```

That single line wires everything:

- **Routes** — `GET /sample` is served by the host's front controller.
- **Entities** — `VendorModule\Entity\SampleEntity` joins the ORM schema.
- **Migrations** — `bin/altair db:migrate` applies this module's migrations
  alongside the host's, sharing the tracking table.
- **Services** — bindings from `Module::apply()` are available in the container.

## Layout

```
src/
  Module.php                     the entry point registered by the host
  Domain/SampleService.php       business logic behind GET /sample
  Http/Actions/SampleAction.php  Action -> Domain -> Responder wiring
  Http/Inputs/SampleInput.php    typed request DTO
  Http/Responders/SampleResponder.php
  Entity/SampleEntity.php        a Cycle-annotated entity
database/migrations/             this module's migrations
tests/ModuleTest.php             proves the module wires up
```

Drop any capability you don't need by removing its interface from the
`implements` list in `src/Module.php`. A service-only module need implement
only `Altair\Module\Contracts\ModuleInterface`.

## Test

```bash
composer install
vendor/bin/phpunit
```
