# univeros/sanitation  ·  Altair\Sanitation

**Purpose:** Composable input sanitation that transforms untrusted input into a safe, canonical form before it reaches your domain logic.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `FilterInterface` | `parse(mixed)` | `mixed` | extends `MiddlewareInterface` |
| `FiltersRunnerInterface` | `withFilters(array)` | `FiltersRunnerInterface` | extends `MiddlewareRunnerInterface` |
| `PayloadInterface` | _(marker)_ |  | extends `PayloadInterface`; constants: `ATTRIBUTE_KEY`, `ATTRIBUTE_SUBJECT` |
| `ResolverInterface` | `__invoke(mixed)` | `FilterInterface` |  |
| `SanitizableInterface` | `getFilters()` | `FilterCollection` |  |
| `SanitizerInterface` | `getPayload()` | `PayloadInterface\|null` |  |
|  | `sanitize(SanitizableInterface)` | `SanitizableInterface` |  |

## Concrete classes

- `AbstractFilter` _(abstract)_ — implements `FilterInterface`, `MiddlewareInterface`
- `AlphaFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `AlphaNumFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `BetweenFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `BooleanFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `CallbackFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `DateTimeFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `FilterCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `FilterResolver` — implements `ResolverInterface`
- `FiltersRunner` — implements `FiltersRunnerInterface`, `MiddlewareRunnerInterface`
- `IntegerFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `LowerCaseFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `MaxFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `MaxStrLengthFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `MinFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `MinStrLengthFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `RegexFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `SanitationConfiguration` — implements `ConfigurationInterface`
- `Sanitizer` — implements `SanitizerInterface`
- `TitleCaseFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `TrimFilter` — implements `FilterInterface`, `MiddlewareInterface`
- `UpperCaseFilter` — implements `FilterInterface`, `MiddlewareInterface`

## Request attribute conventions

| Constant | Value | Declared on |
|---|---|---|
| `ATTRIBUTE_KEY` | `altair:sanitation:attribute` | `PayloadInterface` |
| `ATTRIBUTE_SUBJECT` | `altair:sanitation:subject` | `PayloadInterface` |

## Tests as documentation

- `tests/Sanitation/Collection/FilterCollectionTest.php`
- `tests/Sanitation/Configuration/SanitationConfigurationTest.php`
- `tests/Sanitation/Filter/AbstractFilterTest.php`
- `tests/Sanitation/Filter/AlphaFilterTest.php`
- `tests/Sanitation/Filter/AlphaNumFilterTest.php`
- `tests/Sanitation/Filter/BetweenFilterTest.php`
- `tests/Sanitation/Filter/BooleanFilterTest.php`
- `tests/Sanitation/Filter/CallbackFilterTest.php`
- `tests/Sanitation/Filter/DateTimeFilterTest.php`
- `tests/Sanitation/Filter/IntegerFilterTest.php`
- `tests/Sanitation/Filter/LowerCaseFilterFirstOnlyTest.php`
- `tests/Sanitation/Filter/LowerCaseFilterTest.php`
- `tests/Sanitation/Filter/MaxFilterTest.php`
- `tests/Sanitation/Filter/MaxStrLenFilterTest.php`
- `tests/Sanitation/Filter/MinFilterTest.php`
- `tests/Sanitation/Filter/MinStrLenFilterTest.php`
- `tests/Sanitation/Filter/RegexFilterTest.php`
- `tests/Sanitation/Filter/TitleCaseFilterTest.php`
- `tests/Sanitation/Filter/TrimFilterTest.php`
- `tests/Sanitation/Filter/UpperCaseFilterFirstOnlyTest.php`
- `tests/Sanitation/Filter/UpperCaseFilterTest.php`
- `tests/Sanitation/FiltersRunnerTest.php`
- `tests/Sanitation/Resolver/FilterResolverTest.php`
- `tests/Sanitation/SanitizerTest.php`

## Related packages

- `univeros/configuration`
- `univeros/container`
- `univeros/middleware`
- `univeros/structure`
