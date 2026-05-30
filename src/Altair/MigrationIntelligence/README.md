# Univeros Migration Intelligence

> bin/altair db:migration-plan — proposes safe Cycle migrations from spec/entity diffs with read-only safety checks (NOT NULL backfill, unique dupes, FK orphans, type-cast, large tables) and two-phase rename/type-change plans. Deterministic JSON for agents and CI

## Installation

```bash
composer require univeros/migration-intelligence
```

## Documentation

The full guide for this package lives in the [Univeros documentation](https://github.com/univeros/docs/blob/master/packages/migration-intelligence.md).

## Contributing

This repository is a **read-only mirror** of `src/Altair/MigrationIntelligence/` in [univeros/framework](https://github.com/univeros/framework). All issues, pull requests, and discussion belong there — changes pushed here will be overwritten by the next split.

## License

The Univeros framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
