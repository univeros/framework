# univeros/validation  ·  Altair\Validation

**Purpose:** The Altair Validation package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `PayloadInterface` | _(marker)_ |  | extends `PayloadInterface`; constants: `ATTRIBUTE_FAILURES`, `ATTRIBUTE_KEY`, `ATTRIBUTE_RESULT`, `ATTRIBUTE_SUBJECT` |
| `ResolverInterface` | `__invoke(mixed)` | `RuleInterface` |  |
| `RuleInterface` | `assert(mixed)` | `bool` | extends `MiddlewareInterface` |
| `RulesRunnerInterface` | `withRules(array)` | `RulesRunnerInterface` | extends `MiddlewareRunnerInterface` |
| `ValidatableInterface` | `getRules()` | `RuleCollection` |  |
| `ValidatorInterface` | `getPayload()` | `PayloadInterface\|null` |  |
|  | `validate(ValidatableInterface)` | `bool` |  |

## Concrete classes

- `AbstractRule` _(abstract)_ — implements `MiddlewareInterface`, `RuleInterface`
- `AlphaNumRule` — implements `MiddlewareInterface`, `RuleInterface`
- `AlphaRule` — implements `MiddlewareInterface`, `RuleInterface`
- `BetweenRule` — implements `MiddlewareInterface`, `RuleInterface`
- `BooleanRule` — implements `MiddlewareInterface`, `RuleInterface`
- `CallbackRule` — implements `MiddlewareInterface`, `RuleInterface`
- `CreditCardRule` — implements `MiddlewareInterface`, `RuleInterface`
- `DateTimeRule` — implements `MiddlewareInterface`, `RuleInterface`
- `EmailRule` — implements `MiddlewareInterface`, `RuleInterface`
- `IbanRule` — implements `MiddlewareInterface`, `RuleInterface`
- `InRule` — implements `MiddlewareInterface`, `RuleInterface`
- `IntegerRule` — implements `MiddlewareInterface`, `RuleInterface`
- `IpRule` — implements `MiddlewareInterface`, `RuleInterface`
- `IsbnRule` — implements `MiddlewareInterface`, `RuleInterface`
- `MaxRule` — implements `MiddlewareInterface`, `RuleInterface`
- `MinRule` — implements `MiddlewareInterface`, `RuleInterface`
- `RegexRule` — implements `MiddlewareInterface`, `RuleInterface`
- `RuleCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `RuleResolver` — implements `ResolverInterface`
- `RulesRunner` — implements `MiddlewareRunnerInterface`, `RulesRunnerInterface`
- `SwiftBicRule` — implements `MiddlewareInterface`, `RuleInterface`
- `UrlRule` — implements `MiddlewareInterface`, `RuleInterface`
- `ValidationConfiguration` — implements `ConfigurationInterface`
- `Validator` — implements `ValidatorInterface`
- `ZipCodeRule` — implements `MiddlewareInterface`, `RuleInterface`

## Request attribute conventions

| Constant | Value | Declared on |
|---|---|---|
| `ATTRIBUTE_KEY` | `altair:validation:attribute` | `PayloadInterface` |
| `ATTRIBUTE_FAILURES` | `altair:validation:fail` | `PayloadInterface` |
| `ATTRIBUTE_RESULT` | `altair:validation:result` | `PayloadInterface` |
| `ATTRIBUTE_SUBJECT` | `altair:validation:subject` | `PayloadInterface` |

## Tests as documentation

- `tests/Validation/Collection/RuleCollectionTest.php`
- `tests/Validation/Resolver/RuleResolverTest.php`
- `tests/Validation/Rule/AbstractRuleTest.php`
- `tests/Validation/Rule/AlphaNumRuleTest.php`
- `tests/Validation/Rule/AlphaRuleTest.php`
- `tests/Validation/Rule/BetweenRuleTest.php`
- `tests/Validation/Rule/BooleanRuleTest.php`
- `tests/Validation/Rule/CallbackRuleTest.php`
- `tests/Validation/Rule/CreditCardRuleTest.php`
- `tests/Validation/Rule/DateTimeRuleTest.php`
- `tests/Validation/Rule/EmailRuleTest.php`
- `tests/Validation/Rule/IbanRuleTest.php`
- `tests/Validation/Rule/InRuleTest.php`
- `tests/Validation/Rule/IntegerRuleTest.php`
- `tests/Validation/Rule/IpRuleTest.php`
- `tests/Validation/Rule/IsbnRuleTest.php`
- `tests/Validation/Rule/MaxRuleTest.php`
- `tests/Validation/Rule/MinRuleTest.php`
- `tests/Validation/Rule/RegexRuleTest.php`
- `tests/Validation/Rule/SwiftBicRuleTest.php`
- `tests/Validation/Rule/UrlRuleTest.php`
- `tests/Validation/Rule/ZipCodeRuleTest.php`
- `tests/Validation/RulesRunnerTest.php`
- `tests/Validation/ValidatorTest.php`

## Related packages

- `univeros/configuration`
- `univeros/container`
- `univeros/middleware`
- `univeros/structure`
