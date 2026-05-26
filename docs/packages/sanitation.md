# Sanitation

Transform untrusted input into a safe, canonical form before it reaches your domain logic.

**Package:** `univeros/sanitation`
**Namespace:** `Altair\Sanitation`

---

## Introduction

Sanitation and validation are complementary but distinct concerns. Validation rejects input that does not conform to a rule — it answers the question "is this value acceptable?" and returns a pass/fail result without changing the data. Sanitation transforms input into a safe canonical form — it answers the question "what should this value look like?" and returns a cleaned copy. You sanitize before you validate: strip the spaces before you check whether the remaining string is an email address.

PHP's built-in `ext-filter` (`filter_var`, `FILTER_SANITIZE_*`) provides a flat list of sanitization constants, but it does not compose cleanly. Applying several transformations in sequence requires chaining multiple `filter_var` calls by hand, with no standard way to carry parameters or to insert domain-specific steps. HTML-purifier libraries go in the other direction: they handle the complex but narrow problem of stripping unsafe markup from rich text, and they are not designed for general scalar transformation.

This package occupies the middle ground. Each filter is a small object that implements `FilterInterface` and exposes a single `parse(mixed $value): mixed` method. Filters are composable: you attach an ordered list of them to a field name, and the `Sanitizer` runs each one in turn, passing the output of one as the input to the next. The mechanism is built on the same middleware pipeline used elsewhere in the Altair framework, so filters are also usable as standalone `MiddlewareInterface` callables.

The `Sanitizer` is driven by objects that implement `SanitizableInterface`. Any object — a DTO, a form model, a command object — can carry its own filter map by returning a `FilterCollection` from `getFilters()`. The sanitizer clones the subject before it begins, so the original object is never mutated; you receive a cleaned copy. This immutable-by-default design makes sanitation safe to call in any part of the pipeline.

No `ext-intl` requirement is imposed by this package. The only runtime dependencies are `univeros/middleware` (for the pipeline infrastructure), `univeros/structure` (for the `Queue` and `Map` types), `univeros/container` (for `FilterResolver`), and `univeros/configuration` (for `SanitationConfiguration`).

---

## Installation

Install the package via Composer:

```bash
composer require univeros/sanitation
```

PHP 8.3 or later is required. There are no native extension requirements beyond what PHP 8.3 provides by default. The `mbstring` extension is used by `LowerCaseFilter`, `UpperCaseFilter`, and `MaxStrLengthFilter`; it is bundled with PHP 8.3 in most distributions.

If you are consuming the full `univeros/framework` monorepo, this package is already satisfied through the root `replace` map — no separate `require` is needed.

---

## Quick start

Filters are pure value objects. You can call `parse` directly on any filter without wiring up a container or a pipeline. Here is the simplest possible use: strip every non-letter character from a string.

```php
use Altair\Sanitation\Filter\AlphaFilter;
use Altair\Sanitation\Filter\TrimFilter;

// Each filter's parse() method is callable in isolation.
$alpha = new AlphaFilter();
$trim  = new TrimFilter();

$raw     = '  Hello, World! 42  ';
$trimmed = $trim->parse($raw);    // 'Hello, World! 42'
$cleaned = $alpha->parse($trimmed); // 'HelloWorld'

// AlphaFilter uses Unicode character classes (\p{L}), so non-ASCII
// letters are preserved.
$alpha->parse('Ärger88');  // 'Ärger'
$alpha->parse('самоБуква'); // 'самоБуква'
```

For multi-field sanitation on an object, implement `SanitizableInterface` and call `Sanitizer::sanitize`. The sanitizer returns a cleaned clone; the original is untouched.

```php
use Altair\Sanitation\Filter\AlphaFilter;
use Altair\Sanitation\Filter\TrimFilter;
use Altair\Sanitation\Filter\IntegerFilter;
use Altair\Sanitation\Collection\FilterCollection;
use Altair\Sanitation\Contracts\SanitizableInterface;
use Altair\Sanitation\FiltersRunner;
use Altair\Sanitation\Resolver\FilterResolver;
use Altair\Sanitation\Sanitizer;
use Altair\Container\Container;

class RegistrationForm implements SanitizableInterface
{
    public string $username = '';
    public string $age      = '';

    public function getFilters(): FilterCollection
    {
        return new FilterCollection([
            'username' => [TrimFilter::class, AlphaFilter::class],
            'age'      => IntegerFilter::class,
        ]);
    }
}

$form           = new RegistrationForm();
$form->username = '  Alice99  ';
$form->age      = '+25';

$sanitizer = new Sanitizer(new FiltersRunner(new FilterResolver(new Container())));
$cleaned   = $sanitizer->sanitize($form);

// $cleaned->username === 'Alice'
// $cleaned->age      === 25
// $form->username    === '  Alice99  '  (original unchanged)
```

---

## Concepts

### The Filter contract

`FilterInterface` extends `MiddlewareInterface` from `univeros/middleware`. It adds one method:

```php
namespace Altair\Sanitation\Contracts;

interface FilterInterface extends \Altair\Middleware\Contracts\MiddlewareInterface
{
    public function parse(mixed $value): mixed;
}
```

Every concrete filter in the package extends `AbstractFilter`, which implements `__invoke` (the middleware entry point) in terms of `parse`. `__invoke` reads the current subject and attribute key from the payload, calls `parse` on the attribute value, writes the result back, and hands the payload to the next middleware. You only need to implement `parse` when writing a custom filter.

### Composing filters

Filters are applied left-to-right. When you assign an array of filters to a field, the sanitizer pipes the field value through each filter in declaration order. The output of one filter is the input to the next.

```php
// 'HELLo WoRlD'  → LowerCaseFilter → 'hello world'
//                 → UpperCaseFilter (firstOnly: true) → 'Hello world'
'name' => [
    LowerCaseFilter::class,
    ['class' => UpperCaseFilter::class, ':firstOnly' => true],
],
```

A single filter can be specified as a bare class name string or as an array with a `class` key plus constructor argument entries prefixed with `:`. The `FilterResolver` handles both forms, using the Altair DI container to instantiate classes that require constructor arguments.

### The sanitizer and its pipeline

`Sanitizer::sanitize(SanitizableInterface $subject): SanitizableInterface` is the main entry point. It:

1. Clones the subject immediately (immutability guarantee).
2. Wraps the clone and all sanitizable attribute values in a `Payload` object.
3. Iterates over each key in the subject's `FilterCollection`.
4. For each key, builds a fresh `FiltersRunner` queue from the filter list and invokes it against the payload.
5. Returns the cleaned clone from the payload.

The payload travels through the filter pipeline carrying two well-known attributes:

| Constant | Value | Purpose |
|---|---|---|
| `PayloadInterface::ATTRIBUTE_SUBJECT` | `'altair:sanitation:subject'` | The object being sanitized |
| `PayloadInterface::ATTRIBUTE_KEY` | `'altair:sanitation:attribute'` | The current field name |

Because the pipeline is built on `univeros/middleware`, you can insert any `MiddlewareInterface` into a filter chain — for example, a logging middleware that records every transformation for auditing.

### Multi-key shorthand

A `FilterCollection` key can be a comma-separated list of field names. Whitespace around the commas is stripped automatically. This is useful when the same filter sequence applies to several fields.

```php
new FilterCollection([
    'firstName, lastName' => [TrimFilter::class, AlphaFilter::class],
]);
```

---

## Usage

### Filter catalogue

All filters live under `Altair\Sanitation\Filter\`. Filters that require constructor arguments are shown with their parameter names (prefixed `:` when used in array notation).

| Filter class | Description | Constructor args |
|---|---|---|
| `AlphaFilter` | Removes every character that is not a Unicode letter (`\p{L}`). Preserves non-ASCII letters (Cyrillic, Arabic, CJK, etc.). | — |
| `AlphaNumFilter` | Removes every character that is not a Unicode letter (`\p{L}`) or decimal digit (`\p{Nd}`). | — |
| `BetweenFilter` | Clamps a value to the closed range `[$min, $max]`. Works with any comparable scalar. | `:min`, `:max` |
| `BooleanFilter` | Normalizes truthy strings (`'yes'`, `'on'`, `'true'`, `'1'`) to `true` and falsy strings (`'no'`, `'off'`, `'false'`, `'0'`) to `false`. Returns `null` for non-scalar input. Strings that are neither truthy nor falsy are cast to `(bool)`. | — |
| `CallbackFilter` | Delegates transformation to a callable you supply. The callable receives the raw value and must return the cleaned value. | `:callable` |
| `DateTimeFilter` | Parses the value as a date/time string and reformats it using a PHP date format. Returns `null` for empty, non-scalar, ambiguous, or invalid input. Default format is `'Y-m-d H:i:s'`. | `:format` |
| `IntegerFilter` | Converts numeric scalars to `int`. Handles leading `+`/`-` signs and scientific notation (`(int)(float)'1E5'` = `100000`). Returns `null` for non-numeric strings or non-scalar input. | — |
| `LowerCaseFilter` | Converts a string to lower case via `strtolower`. When `$firstOnly` is `true`, lowers only the first character using `mb_substr` and leaves the rest unchanged. Returns `null` for non-scalar input. | `:firstOnly` (default `false`) |
| `MaxFilter` | Caps a scalar value at `$max`: returns `$max` if the value exceeds it, the value otherwise. Returns `null` for non-scalar input. | `:max` |
| `MaxStrLengthFilter` | Truncates a string to at most `$max` characters using `mb_substr`. Returns `null` for non-string input. | `:max` |
| `MinFilter` | Raises a scalar value to `$min`: returns `$min` if the value is below it, the value otherwise. Returns `null` for non-scalar input. | `:min` |
| `MinStrLengthFilter` | Pads a string to at least `$min` characters using `str_pad`. Padding character and direction are configurable. Returns `null` for non-string input. | `:min`, `:pad` (default `' '`), `:direction` (default `STR_PAD_RIGHT`) |
| `RegexFilter` | Applies `preg_replace($pattern, $replace, $value)`. Returns `null` for non-scalar input. | `:pattern`, `:replace` |
| `TitleCaseFilter` | Capitalizes the first letter of each word via `ucwords`. Returns `null` for non-string input. | — |
| `TrimFilter` | Strips leading and trailing characters via `trim`. Default character mask is `" \t\n\r\0\x0B"`. Returns `null` for non-string input. | `:chars` |
| `UpperCaseFilter` | Converts a string to upper case via `strtoupper`. When `$firstOnly` is `true`, uppers only the first character using `mb_substr` and leaves the rest unchanged. Returns `null` for non-scalar input. | `:firstOnly` (default `false`) |

#### Null-return semantics

Most filters return `null` when given input of the wrong type rather than throwing an exception. `AlphaFilter` and `AlphaNumFilter` are exceptions: they cast the value to string first, so they always return a string (possibly empty). Plan for `null` returns when chaining filters on fields that may receive unexpected types.

### Composing filters in sequence

Assign an array to a field key to apply multiple filters in order. You can mix bare class name strings (for no-argument filters) with `['class' => ..., ':arg' => value]` maps (for parameterized filters) in the same array.

```php
// Normalize a display name: trim whitespace, then title-case it.
'displayName' => [
    TrimFilter::class,
    TitleCaseFilter::class,
],

// Normalize a numeric field: clamp to valid range after casting.
'quantity' => [
    IntegerFilter::class,
    ['class' => BetweenFilter::class, ':min' => 1, ':max' => 999],
],

// Custom pipeline via CallbackFilter.
'slug' => [
    TrimFilter::class,
    LowerCaseFilter::class,
    ['class' => RegexFilter::class, ':pattern' => '/[^a-z0-9]+/', ':replace' => '-'],
    ['class' => RegexFilter::class, ':pattern' => '/^-|-$/', ':replace' => ''],
],
```

### FilterCollection and SanitizableInterface

`FilterCollection` extends `Altair\Structure\Map`. It validates every entry on insertion:

- A string value must be the fully-qualified class name of a class that implements `FilterInterface`.
- An array value must contain a `class` key whose value implements `FilterInterface`.
- A key must be a string. Non-string keys throw `InvalidArgumentException`.

Implement `SanitizableInterface` on any object you want the `Sanitizer` to process:

```php
use Altair\Sanitation\Collection\FilterCollection;
use Altair\Sanitation\Contracts\SanitizableInterface;
use Altair\Sanitation\Filter\TrimFilter;
use Altair\Sanitation\Filter\AlphaNumFilter;

class UserCommand implements SanitizableInterface
{
    public string $handle = '';
    public string $bio    = '';

    public function getFilters(): FilterCollection
    {
        return new FilterCollection([
            'handle' => [TrimFilter::class, AlphaNumFilter::class],
            'bio'    => TrimFilter::class,
        ]);
    }
}
```

The `Sanitizer` accesses the public properties of the subject object. The subject is cloned before any filter runs; the clone is returned from `sanitize()`. The original object is never written.

### Wiring the Sanitizer

Without a DI container you wire the dependencies manually:

```php
use Altair\Container\Container;
use Altair\Sanitation\FiltersRunner;
use Altair\Sanitation\Resolver\FilterResolver;
use Altair\Sanitation\Sanitizer;

$container = new Container();
$sanitizer = new Sanitizer(
    new FiltersRunner(new FilterResolver($container))
);
```

`FilterResolver` uses the container to instantiate filter classes and inject constructor arguments from the array notation. If you do not need parameterized filters you can pass `null` as the resolver; the runner will use the entries directly as callables.

### Per-field sanitation patterns

You can apply the same filter sequence to several fields by using a comma-separated key:

```php
new FilterCollection([
    'firstName, lastName, middleName' => [TrimFilter::class, AlphaFilter::class],
    'age'                             => IntegerFilter::class,
]);
```

Whitespace around commas is stripped automatically. The sanitizer expands the composite key and runs the filter sequence independently for each named field.

---

## Configuration

`SanitationConfiguration` wires the package into the Altair DI container. Apply it once at bootstrap time:

```php
use Altair\Sanitation\Configuration\SanitationConfiguration;
use Altair\Container\Container;

$container = new Container();
(new SanitationConfiguration())->apply($container);

// Now you can make a Sanitizer through the container.
$sanitizer = $container->make(\Altair\Sanitation\Sanitizer::class);
```

`SanitationConfiguration` registers three bindings:

| Interface | Concrete |
|---|---|
| `ResolverInterface` | `FilterResolver` |
| `FiltersRunnerInterface` | `FiltersRunner` |
| `FilterResolver` | Defined with `:container` injected |

The configuration lives in `Altair\Validation\Configuration\SanitationConfiguration` (note: the file's declared namespace is `Altair\Validation\Configuration`, which is a legacy placement — the class is functionally part of the Sanitation package and is imported from there).

---

## Testing

Filters are pure functions: given the same input, `parse` always returns the same output and produces no side effects. This makes them trivial to test without mocking.

```php
use Altair\Sanitation\Filter\AlphaNumFilter;
use Altair\Sanitation\Filter\BetweenFilter;
use Altair\Sanitation\Filter\TrimFilter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AlphaNumFilterTest extends TestCase
{
    // Reason: data providers keep the assertion logic DRY across
    // many input/output pairs — the standard pattern in this test suite.
    #[DataProvider('cases')]
    public function testParse(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, (new AlphaNumFilter())->parse($input));
    }

    public static function cases(): array
    {
        return [
            ['hello123',  'hello123'],
            ['hi!there',  'hithere'],
            ['Ä1',        'Ä1'],       // Unicode letter + digit retained
            ['',          ''],
            [null,        ''],         // cast to string first
        ];
    }
}

class BetweenFilterTest extends TestCase
{
    public function testClampsAboveMax(): void
    {
        $this->assertSame(6, (new BetweenFilter(3, 6))->parse(8));
    }

    public function testClampsBelowMin(): void
    {
        $this->assertSame(3, (new BetweenFilter(3, 6))->parse(1));
    }

    public function testPassesThroughInRange(): void
    {
        $this->assertSame(5, (new BetweenFilter(3, 6))->parse(5));
    }
}
```

The existing test suite uses a shared `AbstractFilterTest` base class that tests both the `parse` method directly and the full `__invoke` middleware path. Extend it for new filters to get both coverage points for free.

To test the full `Sanitizer` pipeline in integration, instantiate the real container rather than mocking:

```php
use Altair\Container\Container;
use Altair\Sanitation\FiltersRunner;
use Altair\Sanitation\Resolver\FilterResolver;
use Altair\Sanitation\Sanitizer;

protected function makeSanitizer(): Sanitizer
{
    return new Sanitizer(new FiltersRunner(new FilterResolver(new Container())));
}
```

---

## Extending

Implement `FilterInterface` (or extend `AbstractFilter`) to add a custom filter. You only need to implement `parse`. `AbstractFilter::__invoke` handles all payload plumbing.

```php
use Altair\Sanitation\Filter\AbstractFilter;

// Normalizes an E.164-ish phone number by stripping all non-digit characters
// except a leading '+'.
final class NormalizePhoneFilter extends AbstractFilter
{
    public function parse(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = (string) $value;
        $prefix = str_starts_with($value, '+') ? '+' : '';

        return $prefix . preg_replace('/\D/', '', $value);
    }
}
```

Use it exactly like any built-in filter:

```php
new FilterCollection([
    'phone' => NormalizePhoneFilter::class,
]);
```

If your filter requires constructor arguments, use the array notation in the collection and rely on `FilterResolver` to instantiate it:

```php
new FilterCollection([
    'phone' => [['class' => NormalizePhoneFilter::class, ':countryCode' => 'US']],
]);
```

---

## Recipes

### Cleaning a user registration form

Apply a layered sanitation sequence before running validation. Trim whitespace first so validation rules see clean values; strip to letters-only for the username; clamp the age to a sane range.

```php
use Altair\Sanitation\Collection\FilterCollection;
use Altair\Sanitation\Contracts\SanitizableInterface;
use Altair\Sanitation\Filter\AlphaFilter;
use Altair\Sanitation\Filter\BetweenFilter;
use Altair\Sanitation\Filter\IntegerFilter;
use Altair\Sanitation\Filter\TrimFilter;

class RegistrationForm implements SanitizableInterface
{
    public string $username = '';
    public string $email    = '';
    public string $age      = '';

    public function getFilters(): FilterCollection
    {
        return new FilterCollection([
            'username, email' => TrimFilter::class,
            'username'        => AlphaFilter::class,
            'age'             => [
                IntegerFilter::class,
                ['class' => BetweenFilter::class, ':min' => 13, ':max' => 120],
            ],
        ]);
    }
}
```

### Normalizing phone number input

Users enter phone numbers in dozens of formats. Strip everything except digits (and an optional leading `+`) so downstream storage and comparison are consistent.

```php
use Altair\Sanitation\Filter\RegexFilter;
use Altair\Sanitation\Filter\TrimFilter;

new FilterCollection([
    'phone' => [
        TrimFilter::class,
        // Remove all characters that are not digits or a leading '+'.
        ['class' => RegexFilter::class, ':pattern' => '/(?!^\+)[^\d]/', ':replace' => ''],
    ],
]);
```

### Stripping HTML tags via CallbackFilter

The package does not ship an HTML-purification filter. Delegate to PHP's built-in `strip_tags` (or a dedicated library such as HTMLPurifier for richer control) through `CallbackFilter`.

```php
use Altair\Sanitation\Filter\CallbackFilter;
use Altair\Sanitation\Filter\TrimFilter;

new FilterCollection([
    'bio' => [
        TrimFilter::class,
        ['class' => CallbackFilter::class, ':callable' => 'strip_tags'],
    ],
]);
```

For HTML-purification with an allowlist (e.g. `<b>`, `<i>`), pass a closure:

```php
['class' => CallbackFilter::class, ':callable' => fn($v) => strip_tags((string) $v, '<b><i><a>')],
```

### Normalizing a date field to ISO 8601

Accept any human-readable date string from the user and store it in `Y-m-d` format so your database layer sees a consistent shape.

```php
use Altair\Sanitation\Filter\DateTimeFilter;
use Altair\Sanitation\Filter\TrimFilter;

new FilterCollection([
    'birthdate' => [
        TrimFilter::class,
        ['class' => DateTimeFilter::class, ':format' => 'Y-m-d'],
    ],
]);

// 'Nov 7, 1979, 12:34pm' → '1979-11-07'
// '07/03/1971'           → '1971-07-03'
// 'not a date'           → null
```

`DateTimeFilter` returns `null` for invalid or ambiguous dates (e.g. `'1979-02-29'` which is not a leap year). Guard for `null` before persisting.

### Building a URL-safe slug field

Combine `TrimFilter`, `LowerCaseFilter`, and `RegexFilter` to derive a clean slug from a user-supplied title. For Unicode-to-ASCII transliteration, pair with `Altair\Common\Support\Inflector::slug`.

```php
use Altair\Sanitation\Filter\LowerCaseFilter;
use Altair\Sanitation\Filter\RegexFilter;
use Altair\Sanitation\Filter\TrimFilter;

new FilterCollection([
    'slug' => [
        TrimFilter::class,
        LowerCaseFilter::class,
        // Replace any run of non-alphanumeric characters with a hyphen.
        ['class' => RegexFilter::class, ':pattern' => '/[^a-z0-9]+/', ':replace' => '-'],
        // Strip leading or trailing hyphens.
        ['class' => RegexFilter::class, ':pattern' => '/^-|-$/', ':replace' => ''],
    ],
]);

// ' Hello, World! 2026 ' → 'hello-world-2026'
```

---

## Related packages

- [`./validation.md`](./validation.md) — The validation package is the natural complement to sanitation. Sanitize first, then validate. The two packages share the same `SanitizableInterface`-style API: implement `getFilters()` for sanitation and `getRules()` for validation on the same object.
- [`./common.md`](./common.md) — The `Altair\Common\Support\Inflector::slug` method pairs well with the slug recipe above when you need Unicode transliteration before applying `RegexFilter`. `Altair\Common\Support\Str` provides byte-safe string utilities that complement the length-bounding filters.
- [`./configuration.md`](./configuration.md) — `SanitationConfiguration` depends on the Altair container (see also `univeros/container`). Read the Configuration package docs if you are managing dotenv-driven bootstrap.

---

## Limitations

- **No HTML purification.** `AlphaFilter` and `RegexFilter` can remove markup characters, but the package ships no safe-HTML-allowlist filter. For rich-text fields that must permit a limited set of tags, delegate to a dedicated library (such as HTMLPurifier or `league/html-to-markdown`) via `CallbackFilter`.
- **`LowerCaseFilter` and `UpperCaseFilter` use `strtolower`/`strtoupper`.** These are locale-sensitive in some PHP builds and do not perform full Unicode case folding. For locale-aware case conversion (e.g. Turkish dotless-i), supply a `CallbackFilter` that calls `mb_strtolower($value, 'UTF-8')`.
- **`TitleCaseFilter` uses `ucwords`.** `ucwords` is ASCII-aware for word boundaries. It does not handle locale-specific capitalization rules.
- **`MinStrLengthFilter` pads with `str_pad`.** `str_pad` operates at the byte level for multi-byte pad strings. Use a single-byte pad character (default space) to avoid corrupted output on multi-byte boundaries.
- **`DateTimeFilter` uses `date_create`.** Ambiguous two-digit year strings and locale-specific date formats (e.g. `'03/07/1971'` being either March 7 or July 3 depending on locale) are resolved by PHP's date parser. Prefer unambiguous ISO 8601 input from the client side and use `DateTimeFilter` only for normalization, not for format correction.
- **Filters receive the raw property value.** `Sanitizer` reads properties from the subject object by name. Properties that do not exist on the subject as public members will cause a runtime error. Declare all sanitizable properties as public on the `SanitizableInterface` implementor.
- **No async or streaming support.** Filters operate synchronously on in-memory scalar values. They are not designed for streaming large payloads.
