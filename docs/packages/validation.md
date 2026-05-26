# Validation

A rule-based input validation library that rejects values not conforming to declared constraints — distinct from the `./sanitation.md` package, which transforms input rather than gatekeeping it.

**Package:** `univeros/validation`
**Namespace:** `Altair\Validation`

---

## Introduction

The Validation package enforces a strict boundary between your application and untrusted data. When a value arrives — from a request body, a CLI argument, a database row, or an API payload — you describe what "valid" means using one or more composable rules, then run a validator to decide whether to proceed or reject. Nothing is modified; the package answers a single yes-or-no question and, on a no, tells you exactly which fields failed and why.

This distinguishes it from the Sanitation package, whose purpose is transformation rather than judgment. Validation rejects. Sanitation corrects. You will often want both: validate first to establish that the shape of the data is acceptable, then sanitize to normalise the acceptable values before persisting them.

Compared to Symfony Validator, this package is deliberately minimal. There is no annotation system, no constraint groups, no cascade validation, and no built-in translation. That narrowness is intentional: every rule is a plain PHP object implementing a two-method interface, every composition is explicit in code, and the entire pipeline is a thin middleware queue rather than a reflection-heavy graph traversal. You see exactly what runs and in what order.

Compared to Respect/Validation, the package integrates natively into the Altair middleware stack. Rules implement `Altair\Middleware\Contracts\MiddlewareInterface`, which means a rule queue is also a standard PSR-15-style middleware queue. The same `Payload`/`Queue`/`Runner` primitives used throughout the framework apply here, so there is no impedance mismatch when a validation step sits inside a larger HTTP middleware pipeline.

The architecture has four layers. A `RuleInterface` is a single assertion. A `RuleCollection` maps field names to arrays of rules. A `Validator` iterates the collection, runs each field's rules through a `RulesRunner`, and accumulates failures into the shared payload. An optional `ValidationConfiguration` wires the container bindings so you can resolve the whole stack from a DI container.

---

## Installation

Install via Composer:

```bash
composer require univeros/validation
```

The package requires PHP 8.3 or later. Its direct Altair dependencies (`univeros/configuration`, `univeros/container`, `univeros/middleware`, `univeros/structure`) are resolved automatically.

No PHP extensions beyond the standard distribution are required by the package itself. Note that `IbanRule` uses `bcmod` for the ISO 7064 MOD-97-10 check and `IpRule` uses `bccomp`/`ip2long` for network-range comparisons — both functions are part of PHP core on all common platforms, but if you are running a stripped-down Docker image you should verify that `bcmath` is available.

If you are consuming the full `univeros/framework` monorepo, the package is already satisfied through the root `replace` map.

---

## Quick start

The fastest path to validating a named set of fields is to implement `ValidatableInterface` on the object that carries your data, then pass it to a `Validator`.

```php
use Altair\Container\Container;
use Altair\Validation\Collection\RuleCollection;
use Altair\Validation\Contracts\ValidatableInterface;
use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Resolver\RuleResolver;
use Altair\Validation\Rule\AlphaRule;
use Altair\Validation\Rule\EmailRule;
use Altair\Validation\RulesRunner;
use Altair\Validation\Validator;

// 1. Describe what you want to validate.
class RegistrationForm implements ValidatableInterface
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
    ) {}

    public function getRules(): RuleCollection
    {
        return (new RuleCollection())
            ->put('username', AlphaRule::class)
            ->put('email', EmailRule::class);
    }
}

// 2. Build the validator (once; inject as a service).
$validator = new Validator(
    new RulesRunner(new RuleResolver(new Container()))
);

// 3. Run it.
$form = new RegistrationForm('alice', 'alice@example.com');

if (!$validator->validate($form)) {
    $failures = $validator->getPayload()
        ->getAttribute(PayloadInterface::ATTRIBUTE_FAILURES);
    // ['email' => '"badvalue" is not a valid email address.']
}
```

---

## Concepts

### RuleInterface

Every assertion implements `Altair\Validation\Contracts\RuleInterface`, which extends `Altair\Middleware\Contracts\MiddlewareInterface`. The contract exposes two methods:

- `assert(mixed $value): bool` — the pure boolean check you write when creating a custom rule.
- `__invoke(PayloadInterface $payload, callable $next): PayloadInterface` — the middleware handler implemented by `AbstractRule`. You do not override this; it reads the subject and attribute key from the payload, calls `assert`, and either advances the queue or records a failure.

Because each rule is simultaneously a validation function and a middleware handler, the same object can be used either standalone (`$rule->assert($value)`) or threaded through a `RulesRunner` queue.

### RuleCollection

`RuleCollection` extends `Altair\Structure\Map` and maps string field keys to rule definitions. Each entry's value may be:

- A class name string: `AlphaRule::class`
- An array of class name strings: `[AlphaRule::class, EmailRule::class]`
- An array of array definitions: `[['class' => BetweenRule::class, ':min' => 1, ':max' => 100]]`

Keys may contain a comma-separated list of field names (`'firstName, lastName'`), in which case the same set of rules is applied to each named field individually. `RuleCollection` validates every entry at insertion time and throws `Altair\Validation\Exception\InvalidArgumentException` if a class does not implement `RuleInterface` or if an array definition lacks the `class` key.

### Validator

`Altair\Validation\Validator` implements `ValidatorInterface`. It accepts a `ValidatableInterface` object, extracts the rule collection, and for each key-rule pair creates a fresh `RulesRunner`, sets the current attribute key on the shared payload, and invokes the runner. After all fields are processed, it returns `true` if no failures were recorded.

After a call to `validate`, `getPayload()` returns the final payload, from which you can read:

- `PayloadInterface::ATTRIBUTE_RESULT` — `true` when all rules passed, `false` otherwise.
- `PayloadInterface::ATTRIBUTE_FAILURES` — an associative array of `['fieldName' => 'error message string']`.

### RulesRunner

`RulesRunner` implements `RulesRunnerInterface` (which extends the middleware `MiddlewareRunnerInterface`). It manages a `Queue` of rule entries, resolves each entry to a callable `RuleInterface` via an injected `ResolverInterface`, and invokes them in sequence. Calling `withRules(array $rules): RulesRunnerInterface` replaces the internal queue and returns the same instance.

### RuleResolver

`RuleResolver` bridges the string/array rule definitions stored in `RuleCollection` to concrete `RuleInterface` instances. Given a class name string it calls `Container::make`; given an array definition it passes the extra `:argument` keys as a `Definition`, enabling constructor injection of rule parameters.

### ValidationConfiguration

`ValidationConfiguration` implements the Altair `ConfigurationInterface` pattern. Calling `apply(Container $container)` registers the interface-to-class aliases that `Validator`, `RulesRunner`, and `RuleResolver` need. Use this when bootstrapping from a DI container rather than wiring dependencies manually.

---

## Usage

### The rule catalogue

The following rules ship with the package. All of them extend `AbstractRule` and require no external PHP extensions unless noted.

| Class | Constructor arguments | What it checks |
|---|---|---|
| `AlphaRule` | — | Value contains only Unicode letters (`\p{L}`). Rejects digits, spaces, punctuation, and non-scalar types. |
| `AlphaNumRule` | — | Value contains only Unicode letters and decimal digits (`\p{L}\p{Nd}`). |
| `BetweenRule` | `mixed $min, mixed $max` | Scalar value satisfies `$min <= $value <= $max` (PHP loose comparison). |
| `BooleanRule` | — | Value is a boolean-like scalar accepted by `FILTER_VALIDATE_BOOLEAN` (`true`, `false`, `"1"`, `"0"`, `"yes"`, `"no"`, `"on"`, `"off"`). |
| `CallbackRule` | `callable $callable` | Delegates to the provided callable. Receives the value; must return `bool`. |
| `CreditCardRule` | `string $type` | Card number passes a Luhn mod-10 check and matches the pattern and length for the named card type. Accepts spaces and hyphens in the input. UnionPay numbers skip the Luhn check. Throws `InvalidArgumentException` for unknown types. |
| `DateTimeRule` | — | Value is a `DateTime` instance, or a scalar that `date_create` can parse without warnings. Rejects empty strings. |
| `EmailRule` | — | Value is a string and passes `FILTER_VALIDATE_EMAIL`. Uses PHP's built-in filter, which is deliberately basic. The source comment recommends `egulias/EmailValidator` for production use. |
| `IbanRule` | — | Value is a structurally valid IBAN: 15+ characters, recognised two-letter country code, country-specific body pattern, and ISO 7064 MOD-97-10 checksum. Strips the `IBAN` prefix and non-alphanumeric separators before checking. Supports 60+ country codes. Uses `bcmod`. |
| `InRule` | `mixed $haystack, bool $strict = false` | Value is in an array haystack (`in_array`) or is a substring of a string haystack (`mb_strpos`/`mb_stripos`). |
| `IntegerRule` | — | Value is a PHP `int` or a numeric string whose value equals its integer cast. |
| `IpRule` | `?int $options = null, ?string $range = null` | Value is a valid IP address. Optionally filtered by `FILTER_FLAG_IPV4`, `FILTER_FLAG_IPV6`, or `FILTER_FLAG_NO_PRIV_RANGE`; optionally constrained to a CIDR block, a hyphen-delimited range, or a wildcard pattern. Uses `bccomp` for range comparison. |
| `IsbnRule` | `?int $type = null` | Value is a valid ISBN-10 or ISBN-13. Pass `10` or `13` to restrict to one edition; pass `null` to accept either. Strips hyphens and spaces before checking. |
| `MaxRule` | `mixed $max` | Scalar value satisfies `$value <= $max`. |
| `MinRule` | `mixed $min` | Scalar value satisfies `$value >= $min`. |
| `RegexRule` | `string $pattern` | Scalar value matches the given PCRE pattern (including delimiters). |
| `SwiftBicRule` | — | Value matches the SWIFT/BIC format: 4 letters (institution), 2 letters (country), 2 alphanumerics (location), optional 3 alphanumerics (branch). |
| `UrlRule` | — | Value is a scalar containing no forbidden characters and parses as a URL with a non-empty scheme and host. |
| `ZipCodeRule` | `?string $country = null` | Value matches the postal code pattern for the given ISO 3166-1 alpha-2 country code. Defaults to `'US'`. Patterns cover 150+ territories. Throws `InvalidArgumentException` for unrecognised country codes. |

**Supported `CreditCardRule` types:** `visaelectron`, `carteblanche`, `maestro`, `forbrugsforeningen`, `dankort`, `visa`, `mastercard`, `amex`, `dinersclub`, `discover`, `unionpay`, `jcb`, `solo`, `switch`.

### Composing rules

You describe all rules for a field as an array. Every rule in the array must pass. The runner processes them in the order they appear and short-circuits on the first failure — subsequent rules for that field are not evaluated.

```php
// Both AlphaRule and MinRule must pass for 'username' to be valid.
(new RuleCollection())
    ->put('username', [
        AlphaRule::class,
        ['class' => MinRule::class, ':min' => 3],
    ]);
```

There is no built-in OR (any-of) combinator at the collection level. To express "value must pass at least one of these rules", wrap the logic in a `CallbackRule`.

```php
use Altair\Validation\Rule\CallbackRule;
use Altair\Validation\Rule\EmailRule;
use Altair\Validation\Rule\UrlRule;

// Accept either an email address or a URL.
$emailOrUrl = new CallbackRule(function (mixed $value): bool {
    return (new EmailRule())->assert($value)
        || (new UrlRule())->assert($value);
});

(new RuleCollection())->put('contact', [$emailOrUrl]);
```

Rules that require constructor arguments are defined as arrays with a `class` key and `:paramName` keys for each constructor parameter. The `RuleResolver` passes these to `Container::make` via a `Definition`.

```php
// BetweenRule constructor: __construct(mixed $min, mixed $max)
['class' => BetweenRule::class, ':min' => 18, ':max' => 99]
```

### Per-field validation

`Validator::validate` iterates the `RuleCollection` and applies rules to each field by reading a public property (or public accessor property) of the `ValidatableInterface` subject with the same name as the collection key.

To apply the same rules to multiple fields in one declaration, use a comma-separated key string. Whitespace around commas is stripped.

```php
// 'firstName' and 'lastName' are both validated with AlphaRule.
(new RuleCollection())
    ->put('firstName, lastName', [AlphaRule::class]);
```

Each field is validated independently. A failure on `firstName` does not prevent `lastName` from being validated.

### Error messages

Each rule generates its own error message via the `buildErrorMessage($value): string` method, which is called only when `assert` returns `false`. The message is stored in the payload under the `ATTRIBUTE_FAILURES` key as an associative array keyed by field name.

```php
$validator->validate($form);

$failures = $validator->getPayload()
    ->getAttribute(PayloadInterface::ATTRIBUTE_FAILURES, []);

foreach ($failures as $field => $message) {
    echo "{$field}: {$message}\n";
}
// username: "4nt0n10" have invalid alphabetic character(s)
// email: "not-an-email" is not a valid email address.
```

Because each field maps to a single message string, if you run multiple rules against one field and the second fails after the first passes, only the second rule's message appears. When the first rule fails, subsequent rules for that field do not run, so only the first failure is reported. There is one message slot per field name.

Messages are plain English strings with no translation or placeholder system. To localise messages, write a custom rule class.

### Validation middleware

Because `RuleInterface` extends `MiddlewareInterface`, you can embed a `RulesRunner` directly in any PSR-15-style pipeline. The more common integration point is to wrap the validation step in a dedicated HTTP middleware class that reads the parsed request body, hydrates a `ValidatableInterface` value object, and either passes the request downstream or returns an early error response.

The `univeros/http` package provides the middleware pipeline infrastructure. See [`./http.md`](./http.md) for how to wire a middleware stack. There is no pre-built `ValidationMiddleware` class in this package; you write the glue that is appropriate to your request/response lifecycle.

---

## Configuration

`ValidationConfiguration` is the entry point for DI container bootstrap.

```php
use Altair\Container\Container;
use Altair\Validation\Configuration\ValidationConfiguration;

$container = new Container();
(new ValidationConfiguration())->apply($container);

// The container now resolves:
// ResolverInterface   -> RuleResolver
// RulesRunnerInterface -> RulesRunner
// Validator is a concrete class; make it directly.
$validator = $container->make(Validator::class);
```

Under the hood `apply` sets two aliases and one `Definition`:

- `ResolverInterface::class` aliased to `RuleResolver::class`
- `RulesRunnerInterface::class` aliased to `RulesRunner::class`
- `RuleResolver::class` defined with `':container' => $container` so the resolver can instantiate rules with constructor arguments

If you are wiring the stack manually (for example in tests or small scripts), construct `Validator`, `RulesRunner`, and `RuleResolver` directly, as shown in the Quick start section.

---

## Testing

Rules are pure: `assert` takes a value and returns a bool with no side effects and no external dependencies. This makes unit-testing them trivial.

The `AbstractRuleTest` base class in the test suite encodes the canonical pattern. Subclass it and provide `trueProvider` and `falseProvider` data providers; the base class generates four test methods automatically — two testing `assert` directly and two testing the full middleware invocation path.

```php
use Altair\Middleware\Payload;
use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Rule\EmailRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailRuleTest extends TestCase
{
    #[DataProvider('validEmails')]
    public function testValidEmail(string $email): void
    {
        $this->assertTrue((new EmailRule())->assert($email));
    }

    #[DataProvider('invalidEmails')]
    public function testInvalidEmail(mixed $value): void
    {
        $this->assertFalse((new EmailRule())->assert($value));
    }

    public static function validEmails(): array
    {
        return [['user@example.com'], ['tag+alias@sub.domain.io']];
    }

    public static function invalidEmails(): array
    {
        return [['notanemail'], ['@missing-local'], [123], [null]];
    }
}
```

To test the full middleware path — including the payload attributes written by `AbstractRule::__invoke` — build a payload with `ATTRIBUTE_SUBJECT` and `ATTRIBUTE_KEY` set, invoke the rule as a callable, and inspect `ATTRIBUTE_RESULT` on the returned payload.

```php
$payload = (new Payload())
    ->withAttribute(PayloadInterface::ATTRIBUTE_SUBJECT, ['email' => 'bad'])
    ->withAttribute(PayloadInterface::ATTRIBUTE_KEY, 'email');

$identity = fn(PayloadInterface $p): PayloadInterface => $p;
$result = (new EmailRule())($payload, $identity);

$this->assertFalse($result->getAttribute(PayloadInterface::ATTRIBUTE_RESULT));
$this->assertArrayHasKey('email', $result->getAttribute(PayloadInterface::ATTRIBUTE_FAILURES, []));
```

---

## Extending

### Writing a custom rule

Implement `RuleInterface` directly, or extend `AbstractRule` to inherit the middleware plumbing for free. Extending `AbstractRule` requires implementing two methods.

```php
declare(strict_types=1);

namespace App\Validation\Rule;

use Altair\Validation\Rule\AbstractRule;

class PhoneNumberRule extends AbstractRule
{
    // assert receives the raw value from the payload subject.
    public function assert(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return (bool) preg_match('/^\+?[1-9]\d{6,14}$/', $value);
    }

    protected function buildErrorMessage(mixed $value): string
    {
        return sprintf('"%s" is not a valid E.164 phone number.', $value);
    }
}
```

Once defined, use the class name string or array definition anywhere `RuleCollection` accepts a rule.

```php
(new RuleCollection())->put('phone', PhoneNumberRule::class);
```

Rules that require constructor arguments must declare them in the constructor. Pass them via the array definition syntax so `RuleResolver` can inject them.

```php
class AllowedDomainsRule extends AbstractRule
{
    public function __construct(private readonly array $domains) {}

    public function assert(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $host = parse_url($value, PHP_URL_HOST);
        return $host !== null && in_array($host, $this->domains, true);
    }

    protected function buildErrorMessage(mixed $value): string
    {
        return sprintf('"%s" is not from an allowed domain.', $value);
    }
}

// Usage in a collection:
['class' => AllowedDomainsRule::class, ':domains' => ['example.com', 'example.org']]
```

---

## Recipes

### Validating a user registration form

This is the most common scenario: an HTTP request body mapped to a value object, validated before any persistence occurs.

```php
declare(strict_types=1);

namespace App\Http\Request;

use Altair\Validation\Collection\RuleCollection;
use Altair\Validation\Contracts\ValidatableInterface;
use Altair\Validation\Rule\AlphaRule;
use Altair\Validation\Rule\BetweenRule;
use Altair\Validation\Rule\EmailRule;
use Altair\Validation\Rule\RegexRule;

class RegistrationRequest implements ValidatableInterface
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly string $password,
        public readonly int    $age,
    ) {}

    public function getRules(): RuleCollection
    {
        return (new RuleCollection())
            ->put('username', [
                AlphaRule::class,
                ['class' => BetweenRule::class, ':min' => 3, ':max' => 30],
            ])
            ->put('email', EmailRule::class)
            ->put('password', [
                ['class' => BetweenRule::class, ':min' => 8, ':max' => 128],
                ['class' => RegexRule::class, ':pattern' => '/[A-Z]/'],
            ])
            ->put('age', [
                ['class' => BetweenRule::class, ':min' => 18, ':max' => 120],
            ]);
    }
}
```

Pass it to the validator and inspect failures before proceeding.

```php
if (!$validator->validate($request)) {
    $failures = $validator->getPayload()
        ->getAttribute(PayloadInterface::ATTRIBUTE_FAILURES, []);
    // Return a 422 response with $failures as the error body.
}
```

### Custom business rules using CallbackRule

When a rule is project-specific and unlikely to be reused, `CallbackRule` avoids creating a dedicated class.

```php
use Altair\Validation\Rule\CallbackRule;

// Ensure a username is not already taken — delegates to a repository.
$uniqueUsername = new CallbackRule(
    fn(mixed $value): bool => !$userRepository->existsByUsername((string) $value)
);

(new RuleCollection())->put('username', [$uniqueUsername]);
```

Be aware that `CallbackRule::buildErrorMessage` always returns `"value" is not a valid value.` — write a custom rule class when you need a descriptive message.

### Locale-aware postal code validation

`ZipCodeRule` accepts any ISO 3166-1 alpha-2 country code at construction time. To validate postal codes for a user's self-reported country, pass the country code from the submitted data.

```php
use Altair\Validation\Collection\RuleCollection;
use Altair\Validation\Rule\ZipCodeRule;

// $countryCode comes from a validated (alpha-2) country field earlier in the pipeline.
$postalRule = new ZipCodeRule($countryCode);

// Assert directly — no need to build a full Validator for a single field.
if (!$postalRule->assert($postalCode)) {
    // report error
}
```

Patterns cover 150+ territories. Country codes outside the supported set throw `InvalidArgumentException` at construction, so validate the country field before constructing the rule.

### Optional and dependent fields

The package does not have first-class support for optional fields. The practical approach is to perform a presence check before building your `RuleCollection`, or to use `CallbackRule` to encode the conditional logic.

```php
use Altair\Validation\Rule\CallbackRule;

// 'businessName' is required only when 'accountType' is 'business'.
$conditionalName = new CallbackRule(
    function (mixed $value) use ($form): bool {
        if ($form->accountType !== 'business') {
            return true; // field not required; always passes
        }
        return is_string($value) && trim($value) !== '';
    }
);

(new RuleCollection())->put('businessName', [$conditionalName]);
```

### Financial identifier validation

`IbanRule` and `SwiftBicRule` work together when validating bank transfer recipients. Both can be called directly or composed in a collection.

```php
use Altair\Validation\Rule\IbanRule;
use Altair\Validation\Rule\SwiftBicRule;

$iban  = new IbanRule();
$swift = new SwiftBicRule();

// Direct assertion — fast for single-field checks.
$isValidIban  = $iban->assert('GB29NWBK60161331926819');   // true
$isValidSwift = $swift->assert('NWBKGB2L');                // true

// In a collection:
(new RuleCollection())
    ->put('iban',  IbanRule::class)
    ->put('swift', SwiftBicRule::class);
```

`IbanRule` strips the `IBAN` prefix and whitespace/hyphens before checking, so user-pasted values do not require pre-processing.

---

## Related packages

- [`./sanitation.md`](./sanitation.md) — The Sanitation package is the natural counterpart: it transforms values (trim, strip tags, normalise case) where Validation only judges them. Run validation after sanitation to assess the cleaned data.
- [`./http.md`](./http.md) — The HTTP package provides the middleware pipeline where a validation step typically lives. Because `RuleInterface` is already a `MiddlewareInterface`, rules can be embedded directly in a `RelayRunner`-compatible queue.
- [`./data.md`](./data.md) — The Data package provides entity and DTO base classes. Implement `ValidatableInterface` on a Data entity to give it a native `getRules()` contract, enabling direct validation of persisted value objects.

---

## Limitations

- **No built-in message localisation.** Error messages are hardcoded English strings. There is no translation layer, no message catalogue, and no `%placeholder%` interpolation. To localise, write custom rule subclasses that override `buildErrorMessage`.
- **One failure message per field.** `ATTRIBUTE_FAILURES` stores one string per field key. If multiple rules fail for the same field only the first failure is recorded (because the runner short-circuits on failure). You cannot collect all failures for a field in a single pass.
- **No async rules.** Every rule is synchronous. There is no provision for rules that need to await a network call or a coroutine. Use `CallbackRule` with a synchronous call if you must check an external resource.
- **No built-in optional/nullable handling.** Rules receive the value as-is. A `null` value is passed to `assert` and most rules return `false` for it. There is no `Optional` wrapper or `Nullable` combinator; encode optionality in a `CallbackRule` or at the field-presence-check layer.
- **EmailRule uses PHP's built-in filter, which is permissive.** `FILTER_VALIDATE_EMAIL` accepts strings that real-world mail servers would reject (for example, addresses with unusual local-part characters). The source comments explicitly recommend `egulias/EmailValidator` for production use.
- **AlphaRule and AlphaNumRule are Unicode-aware but do not enforce a script.** They match any Unicode letter (`\p{L}`), so `'самоБуква'` (Cyrillic) and `'αβγ'` (Greek) both pass `AlphaRule`. If your domain requires ASCII-only input, use `RegexRule` with `/^[a-zA-Z]+$/` instead.
- **No OR (any-of) combinator at the collection level.** The package provides AND semantics only: all rules in an array must pass. Express OR logic via `CallbackRule`.
- **No schema serialisation.** There is no way to export or import a `RuleCollection` as JSON or XML. Rules are PHP objects constructed in code.
