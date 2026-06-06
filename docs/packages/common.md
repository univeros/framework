# Common

A collection of pure PHP utilities — string helpers, array manipulation, and a key-value registry — shared across every Altair sub-package.

**Package:** `univeros/common`
**Namespace:** `Altair\Common`

---

## Introduction

The Common package is the lowest-level building block in the Altair framework. It provides a small set of stateless utility classes and one stateful registry that every other sub-package can depend on without pulling in unrelated behaviour.

The package is deliberately narrow in scope. `Arr` covers the array operations that PHP's standard library handles awkwardly — recursive merging with predictable key semantics, dot-path traversal, multi-column indexing, and HTML encoding. `Str` covers the byte-safe string operations that appear repeatedly when processing user input or building HTTP identifiers. Neither class touches the filesystem, the network, or any framework-specific abstraction.

`ArrayRegistry` is the sole stateful class. It pairs a flat key-value store with `Arr::getValue`'s dot-path resolution, giving other packages a convenient place to hold runtime configuration without coupling them to a full DI container or config reader.

`Inflector` and its two collaborators (`Transliterator`, `Pluralizer`) handle English-language string transformations: slugs, CamelCase conversion, pluralization, and ordinals. They depend on PHP's `ext-intl` extension for correct Unicode transliteration, with an ASCII fallback map for environments where `intl` is unavailable.

The package contains no I/O, no HTTP primitives, and no framework-specific interfaces. You can use any class from it inside a pure domain library with no further dependencies.

---

## Installation

Install via Composer:

```bash
composer require univeros/common
```

The only non-optional runtime requirement is `ext-intl` (for `Transliterator`). `Arr` and `Str` work without it; `Inflector::slug` degrades gracefully to a Latin-only fallback map when `intl` is absent.

If you are consuming the full `univeros/framework` monorepo, `univeros/common` is already satisfied through the root `replace` map — no separate `require` is needed.

---

## Quick start

The two most frequently reached-for classes are `Arr` (static) and `Str` (instantiated). Here is a minimal example that covers a common scenario: reading a nested config array and checking a string prefix.

```php
use Altair\Common\Support\Arr;
use Altair\Common\Support\Str;

// Read a nested value using a dot-path; return a default when absent.
$timeout = Arr::getValue($config, 'database.pool.timeout', 30);

// Confirm a string starts with the expected scheme.
$str = new Str();
if (!$str->startsWith($url, 'https', caseSensitive: false)) {
    throw new \RuntimeException('Only HTTPS URLs are accepted.');
}

// Build a lookup map from a list of records.
$byId = Arr::map($records, 'id', 'name');
// ['42' => 'Alice', '43' => 'Bob', ...]
```

For the registry, seed it once and pass it as a dependency:

```php
use Altair\Common\Registry\ArrayRegistry;

$registry = new ArrayRegistry([
    'mailer' => ['host' => 'smtp.example.com', 'port' => 587],
]);

$host = $registry->get('mailer.host');          // 'smtp.example.com'
$registry->set('mailer.retries', 3);            // fluent; returns $registry
```

---

## Concepts

The package is organized around two patterns.

**Static utility classes** (`Arr`, `Str`) expose methods that take all their inputs as arguments and return new values. They hold no state between calls. You call them directly without instantiating a service or registering anything. `Arr` is entirely static; `Str` requires instantiation but also holds no mutable state.

**A stateful registry** (`ArrayRegistry`) holds a mutable array and exposes `get` and `set`. It uses `Arr::getValue` internally, so its `get` method inherits full dot-path resolution. The registry is designed to be constructed once, optionally seeded with data, and then injected wherever it is needed.

The boundary between the two patterns is intentional: pure transformations live in the static helpers and the registry is kept to the minimum interface (`RegistryInterface`) that other packages need to retrieve and store values without caring about the underlying storage.

---

## Usage

### `Arr` helpers

`Arr` is a static class. All methods return new arrays; none of them modify the input in place, with the exception of `remove`, `removeValue`, and `multisort`, which operate on references as a deliberate design choice matching their semantics (extract-and-delete, bulk-remove-by-value, in-place sort).

#### `Arr::getValue`

Use `getValue` whenever you need to read a nested value from an array without writing a chain of `isset` guards. You pass a dot-separated key path and an optional default.

```php
// Read a flat key.
$name = Arr::getValue($row, 'username');

// Read a nested key with a dot path.
$street = Arr::getValue($address, 'location.street');

// Supply a fallback when the key is absent.
$limit = Arr::getValue($options, 'pagination.limit', 25);

// Use a Closure to compute the value dynamically.
$full = Arr::getValue($user, fn($u, $d) => $u['first'] . ' ' . $u['last']);
```

If the array already contains a key that literally contains a dot (for example `'x.y'`), `getValue` returns that key's value directly before descending — it does not split on the dot. To force traversal, pass an array of key segments instead: `['x', 'y']`.

#### `Arr::merge`

Use `merge` instead of `array_merge` when you have nested associative arrays and want string-keyed sub-arrays merged recursively rather than replaced wholesale.

```php
$base    = ['db' => ['host' => 'localhost', 'port' => 3306]];
$overlay = ['db' => ['port' => 5432, 'name' => 'app']];

$result = Arr::merge($base, $overlay);
// ['db' => ['host' => 'localhost', 'port' => 5432, 'name' => 'app']]
```

Unlike `array_merge_recursive`, string-keyed scalar values in `$overlay` overwrite those in `$base`. Integer-keyed values are appended, preserving both lists.

#### `Arr::index`

Use `index` to re-key a list of records by one of their fields. This is the canonical way to convert a flat result set into a lookup-by-id structure.

```php
$rows = [
    ['id' => 'a', 'label' => 'Alpha'],
    ['id' => 'b', 'label' => 'Beta'],
];

$byId = Arr::index($rows, 'id');
// ['a' => ['id' => 'a', 'label' => 'Alpha'], 'b' => [...]]
```

Pass a third argument to group by a secondary field instead of overwriting duplicate keys. You can nest multiple grouping levels and use closures in place of field names.

#### `Arr::map`

Use `map` to derive a key-value pair from each record in a list. The result is a flat associative array where one column becomes the key and another becomes the value.

```php
$pairs = Arr::map($rows, 'id', 'label');
// ['a' => 'Alpha', 'b' => 'Beta']

// Group by a third field.
$grouped = Arr::map($rows, 'id', 'label', 'category');
```

#### `Arr::filter`

Use `filter` to allow-list specific keys (and optionally sub-keys) from an array. Prefix a key with `!` to explicitly exclude it after including a parent.

```php
$safe = Arr::filter($_POST, ['username', 'email']);

// Include 'profile' but exclude 'profile.password'.
$partial = Arr::filter($data, ['profile', '!profile.password']);
```

#### `Arr::keyExists`

Use `keyExists` when you need case-insensitive key lookup, which `array_key_exists` does not support.

```php
Arr::keyExists('Content-Type', $headers);                  // true  (exact)
Arr::keyExists('content-type', $headers, false);           // true  (case-insensitive)
```

#### Other helpers

| Method | Purpose |
|---|---|
| `remove($array, $key, $default)` | Extract a key's value and unset it from the array |
| `removeValue($array, $value)` | Remove all elements by value; return those removed |
| `getColumn($array, $name, $keepKeys)` | Extract one column from a list of rows |
| `multisort($array, $key, ...)` | Sort a list of rows by one or more columns |
| `htmlEncode($array, $valuesOnly)` | Recursively HTML-encode string values |
| `htmlDecode($array, $valuesOnly)` | Recursively HTML-decode string values |
| `isAssociative($array, $allStrings)` | Test whether all (or any) keys are strings |
| `isIndexed($array, $consecutive)` | Test whether all keys are integers, optionally consecutive |
| `isIn($needle, $haystack, $strict)` | `in_array` that also accepts `Traversable` |
| `isSubset($needles, $haystack, $strict)` | Test that every element of `$needles` is in `$haystack` |

---

### `Str` helpers

`Str` is an instantiated class. All methods are instance methods. They operate at the byte level using `mb_strlen` and `mb_substr` with the `'8bit'` encoding by default, which means they count and slice raw bytes, not Unicode code points. Pass a different encoding constant where you need character-level semantics.

#### `byteLength` and `byteSubString`

Use these when you need to measure or slice binary-safe strings — for example, when hashing passwords, computing HMAC payloads, or slicing JWT segments.

```php
$str = new Str();

$len = $str->byteLength($token);              // bytes, not characters
$head = $str->byteSubString($token, 0, 16);   // first 16 bytes
```

#### `truncate` and `truncateWords`

Use `truncate` to cap a string at a character count for display. Use `truncateWords` to respect word boundaries.

```php
$preview = $str->truncate($article, 160);
// 'The quick brown fox jumps over the lazy...'

$teaser = $str->truncateWords($article, 20);
// Stops after the 20th word, never mid-word.
```

Both append `'...'` by default. Pass a custom suffix as the third argument.

#### `startsWith` and `endsWith`

Use these for prefix and suffix checks. Both accept a `$caseSensitive` flag and an `$encoding` argument. An empty needle always returns `true`, consistent with the mathematical definition of a prefix.

```php
$str->startsWith($path, '/api/v1');
$str->endsWith($filename, '.json', caseSensitive: false);
```

#### Other helpers

| Method | Purpose |
|---|---|
| `countWords($value)` | Count whitespace-delimited words (Unicode-safe) |
| `replaceFirst($search, $replace, $subject)` | Replace only the first occurrence of a substring |
| `replaceLast($search, $replace, $subject)` | Replace only the last occurrence of a substring |

---

### `Inflector`, `Pluralizer`, and `Transliterator`

`Inflector` handles English-language string shape transformations: slugs, case conversions, pluralization, and ordinals. It depends on `Transliterator` for Unicode-to-ASCII conversion and `Pluralizer` for English morphology.

Instantiate it by wiring the two collaborators:

```php
use Altair\Common\Support\Inflector;
use Altair\Common\Support\Pluralizer;
use Altair\Common\Support\Transliterator;

$inflector = new Inflector(new Transliterator(), new Pluralizer());
```

#### `slug`

Use `slug` to produce URL-safe identifiers from arbitrary Unicode input. It runs the string through `Transliterator::transliterate`, strips non-alphanumeric characters, and collapses separators.

```php
$inflector->slug('Ärger mit Umlauten!');   // 'arger-mit-umlauten'
$inflector->slug('Post title here', '_');  // 'post_title_here'
```

#### Case conversion methods

```php
$inflector->camel('send_email');         // 'SendEmail'
$inflector->variable('send_email');      // 'sendEmail'
$inflector->underscore('SendEmail');     // 'send_email'
$inflector->camelToId('PostTag');        // 'post-tag'
$inflector->idToCamel('post-tag');       // 'PostTag'
$inflector->camelToWords('PostTag');     // 'Post Tag'
$inflector->humanize('user_id');         // 'User'
$inflector->title('send_email');         // 'Send email'
```

#### Pluralization and ORM helpers

```php
$inflector->ordinal(13);                 // '13th'
$inflector->classify('people');          // 'Person'  (singular + CamelCase)
$inflector->table('Person');             // 'people'  (underscore + plural)
```

`Pluralizer` contains an extensive special-case list for irregular English words and handles common morphological rules. It does not handle non-English words.

#### `Transliterator` modes

`Transliterator` exposes three ICU transliteration rule constants:

| Constant | Rule | Result character set |
|---|---|---|
| `TRANSLITERATE_LOOSE` | `Any-Latin; Latin-ASCII; [-￿] remove` | Basic Latin (default) |
| `TRANSLITERATE_MEDIUM` | `Any-Latin; Latin-ASCII` | Latin-1 ASCII |
| `TRANSLITERATE_STRICT` | `Any-Latin; NFKD` | Any UTF-8 |

Switch the mode when you need a less aggressive normalization:

```php
$transliterator = (new Transliterator())
    ->setTransliterator(Transliterator::TRANSLITERATE_MEDIUM);
```

When `ext-intl` is absent, `transliterate` falls back to a static character map covering the Western European Latin supplement (U+00C0–U+00FF). Non-Latin characters outside that range are passed through unchanged.

---

### `ArrayRegistry`

`ArrayRegistry` implements `RegistryInterface`, which defines two methods: `get(string $key, mixed $default = null): mixed` and `set(string $key, mixed $value): static`.

The implementation stores values in a plain PHP array and delegates all reads to `Arr::getValue`, so you get dot-path access for free.

```php
use Altair\Common\Registry\ArrayRegistry;

$registry = new ArrayRegistry([
    'cache' => ['ttl' => 3600, 'driver' => 'redis'],
]);

// Flat key.
$driver = $registry->get('cache.driver');         // 'redis'

// Missing key with fallback.
$prefix = $registry->get('cache.prefix', 'app:'); // 'app:'

// Set returns the same instance for method chaining.
$registry
    ->set('cache.driver', 'memcached')
    ->set('cache.ttl', 900);
```

`set` stores values at the top-level key only — it does not perform deep writes via dot notation. To write a nested value, either seed the constructor with the full structure or set the entire sub-array at once:

```php
$registry->set('cache', ['driver' => 'memcached', 'ttl' => 900]);
```

`ArrayRegistry` is mutable by design. If you need an immutable configuration store, use the `univeros/configuration` package, which wraps dotenv files and exposes a read-only interface.

---

## Configuration

This package has no configuration of its own. It is a library of utilities. Consume individual classes directly; no service provider or bootstrap step is required.

---

## Testing

Because `Arr` and `Str` are stateless, testing them is straightforward: call the method, assert the return value.

```php
use Altair\Common\Support\Arr;
use Altair\Common\Support\Str;
use PHPUnit\Framework\TestCase;

class ArrTest extends TestCase
{
    public function testGetValueByDotPath(): void
    {
        $this->assertSame(
            'alice',
            Arr::getValue(['user' => ['name' => 'alice']], 'user.name'),
        );
    }
}

class StrTest extends TestCase
{
    private Str $str;

    protected function setUp(): void
    {
        $this->str = new Str();
    }

    public function testTruncateAppendsSuffix(): void
    {
        $this->assertSame('hello...', $this->str->truncate('hello world', 5));
    }
}
```

`ArrayRegistry` requires no mocking because it holds no external dependencies. Instantiate it directly in tests.

Tests for this package live under `tests/Common/` and mirror the `src/Altair/Common/` layout:

```
tests/Common/
    Registry/ArrayRegistryTest.php
    Support/ArrTest.php
    Support/StrTest.php
```

There are no fixtures files for this package. PHPUnit 12 attribute style (`#[Test]`, `#[DataProvider]`) is preferred for new test methods.

---

## Extending

`Arr` and `Str` are concrete classes, not interfaces. They are not designed to be subclassed. If you need a different behaviour, write a standalone function or a wrapper class rather than extending them.

`ArrayRegistry` can be extended by subclassing it and overriding `get` or `set`. The more idiomatic approach is to implement `RegistryInterface` from scratch, which keeps your implementation decoupled from `Arr`.

---

## Recipes

### Building a URL slug from arbitrary user input

`Inflector::slug` handles Unicode input reliably when `ext-intl` is present.

```php
$inflector = new Inflector(new Transliterator(), new Pluralizer());

$slug = $inflector->slug($request->getParsedBody()['title'] ?? '');
// Input:  'Héllo, Wörld! (2026)'
// Output: 'hello-world-2026'
```

### Merging layered configuration arrays

Use `Arr::merge` to apply environment-specific overrides on top of a default configuration without losing keys that are absent from the overlay.

```php
$defaults = require 'config/default.php';
$env      = require 'config/' . APP_ENV . '.php';

$config = Arr::merge($defaults, $env);
// Deep string keys in $env overwrite $defaults; integer-keyed lists are appended.
```

### Reading optional nested config values safely

Use `Arr::getValue` with a default to avoid nested `isset` chains in service constructors.

```php
$dsn     = Arr::getValue($config, 'database.primary.dsn');
$timeout = Arr::getValue($config, 'database.primary.timeout', 5);
$options = Arr::getValue($config, 'database.primary.options', []);
```

### Indexing a database result set for O(1) lookups

After fetching rows from a query, use `Arr::index` to re-key by primary ID rather than searching the list linearly on every access.

```php
$users  = $db->query('SELECT id, name, role FROM users');
$byId   = Arr::index($users, 'id');

$author = $byId[$post['author_id']] ?? null;
```

### Deriving an ORM table name from a class name

`Inflector::table` converts a PHP class name to its conventional database table name.

```php
$table = $inflector->table('BlogPost');  // 'blog_posts'
$table = $inflector->table('Person');    // 'people'
```

The inverse, `classify`, maps a table name back to a singular CamelCase class name:

```php
$class = $inflector->classify('blog_posts');  // 'BlogPost'
```

### Filtering untrusted input arrays

Use `Arr::filter` to allow-list fields from a raw request body before passing data further into the system.

```php
$validated = Arr::filter($request->getParsedBody(), [
    'title',
    'body',
    'meta.description',
    '!meta.internal_notes',
]);
```

---

## Related packages

- [`./configuration.md`](./configuration.md) — The Configuration package uses `ArrayRegistry` as the underlying store when loading dotenv files. Read it if you need read-only, file-backed configuration.
- [`./structure.md`](./structure.md) — The Structure package provides typed collection classes (Map, Set, Queue) when you need more than a raw array with helper methods.
- [`./container.md`](./container.md) — The Container package (PSR-11) uses `RegistryInterface` for service binding. Understanding Common's registry contract helps when reading Container internals.

---

## Limitations

- **`Str` operates at the byte level by default.** Methods use `'8bit'` encoding. They do not count or slice Unicode grapheme clusters. If you pass multi-byte UTF-8 to `truncate`, the character count is a byte count and the output may split a multi-byte sequence. Pass `'UTF-8'` as the `$encoding` argument where character-level semantics are required.
- **No Unicode normalization in `Str`.** `Str` provides no NFC/NFD normalization. If you compare strings that originate from different sources (e.g., user input vs. stored data), normalize them upstream before calling `startsWith` or `endsWith`.
- **`Inflector` handles English morphology only.** `Pluralizer` contains English irregular words and English morphological rules. It does not pluralize or singularize words from other languages.
- **`Arr::filter` is one level deep.** The filter syntax supports `'parent.child'` paths, but not deeper nesting (`'a.b.c'` is not supported). For deep allow-listing, nest multiple `filter` calls.
- **`ArrayRegistry::set` writes only to the top level.** Dot notation in `set` is not interpreted as a path. Only `get` resolves dots. This is intentional — the registry is a flat key-value store whose values may themselves be arrays.
- **`Arr::remove` and `Arr::removeValue` mutate their argument.** This is the only deliberate mutation in the package. Both accept their array by reference. Callers that require immutability should copy the array first.
