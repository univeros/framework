# Data

A trait-composed, immutable-by-default data-object layer with JSON serialization, PHP `Serializable` support, and Carbon-powered date attribute mutators.

---

## Composer and namespace

- **Package:** `univeros/data`
- **Namespace:** `Altair\Data`
- **Minimum PHP:** 8.3

---

## Introduction

The Data package provides the structural foundation for typed, immutable data objects in the Altair framework. It is the right tool when you need an object that carries named attributes, can survive a round-trip through `json_encode` / `json_decode`, and enforces that no caller ever modifies its state after construction.

A **DTO** (Data Transfer Object) carries data across a process boundary but typically has no behaviour. A Data object is similar: it is attribute-oriented and carries no persistence logic. The difference is that it goes further by providing a standardised interface — `DataObjectInterface` — that every consuming layer (validation, HTTP responders, view models, cache and queue payloads) can depend on without knowing the concrete class.

This package is **not an ORM**, and it owns no repository contracts. There is no query builder, no relationship loading, no lazy hydration, and no database connection. Persistence is a separate concern: the `univeros/persistence` package owns the framework's single `RepositoryInterface` and is where a Data object can gain persistence, relations, and type coercion. The dependency arrow is one-way — Persistence may depend on Data, never the reverse — which keeps this package a zero-dependency leaf.

Date fields get first-class treatment. Any string attribute that holds a parseable date can be read back as a `Carbon\Carbon` instance via `asCarbonDate()`, or as a formatted string via `asDateString()`. This removes the boilerplate of constructing Carbon objects at the call site and keeps the conversion logic co-located with the entity.

The implementation is entirely trait-based. There is no abstract base class to extend. You compose the four traits into a `final` (or open) class and implement `DataObjectInterface`. This design keeps the package usable alongside other base classes your architecture may already impose.

---

## Installation

The package has no runtime dependencies beyond PHP 8.3 and `nesbot/carbon` (which the root monorepo already pulls in).

```bash
composer require univeros/data
```

---

## Quick start

Create a small entity by implementing `DataObjectInterface` and composing the four traits. Pass initial values as an associative array to the constructor.

```php
<?php declare(strict_types=1);

use Altair\Data\Contracts\DataObjectInterface;
use Altair\Data\Traits\ImmutableAttributesAwareTrait;
use Altair\Data\Traits\JsonSerializableAwareTrait;
use Altair\Data\Traits\DateAttributeMutatorAwareTrait;
use Altair\Data\Traits\SerializeAwareTrait;

final class Product implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use DateAttributeMutatorAwareTrait;
    use SerializeAwareTrait;

    private ?int $id = null;
    private ?string $name = null;
    private ?string $created_at = null;
}

// Populate on construction.
$product = new Product(['id' => 1, 'name' => 'Orion Lens', 'created_at' => '2026-01-15 09:00:00']);

// Read an attribute.
echo $product->name;                           // 'Orion Lens'

// Derive a Carbon instance for a date attribute.
$dt = $product->asCarbonDate('created_at');    // Carbon\Carbon

// Produce a new instance with changed values — original is unchanged.
$renamed = $product->withData(['name' => 'Vega Lens']);

// Serialize to JSON.
echo json_encode($product);                    // {"id":1,"name":"Orion Lens","created_at":"2026-01-15 09:00:00"}
```

---

## Concepts

### Contracts

`DataObjectInterface` extends three standard contracts:

| Contract | Source |
|---|---|
| `ArrayableInterface` | `Altair\Data\Contracts\ArrayableInterface` |
| `JsonSerializable` | PHP built-in |
| `Serializable` | PHP built-in |

`ArrayableInterface` declares a single method, `toArray(): array`. `DataObjectInterface` builds on it by adding `has(string $key): bool`, `get(string $key): mixed`, and `withData(array $data): static`. These three methods are the stable API that consuming code should target.

### Attribute access

Attributes are declared as **typed private properties** on your entity class. The `ImmutableAttributesAwareTrait` constructor reads an incoming array, intersects its keys against the object's declared properties, and copies matching values in. Unknown keys are silently discarded — this makes it safe to pass raw request data directly without worrying about mass-assignment of undeclared fields.

After construction, `__get()` delegates to `get()`, giving you clean property-style reads (`$entity->name`). `__set()` and `__unset()` both throw `Altair\Data\Exception\RuntimeException`, enforcing immutability at runtime. Any attribute update must go through `withData()`, which clones the object and applies changes to the clone.

### Date attribute mutators

Date mutators operate on any attribute that holds a date string parseable by `Carbon\Carbon`. You do not register the attribute in advance. You simply call `asCarbonDate(string $key)` or `asDateString(string $key, string $format = 'r')` at read time, passing the attribute name.

### JSON serialization

`jsonSerialize()` — called automatically by `json_encode()` — delegates to `toArray()`. The result is a flat, recursively expanded array. Nested `ArrayableInterface` values are expanded too.

### PHP serialization

`serialize()` produces a PHP-native serialized string of `toArray()`'s output. `unserialize()` restores the attributes, accepting any class allowed by the `allowed_classes` option. This makes entities safe to store in session handlers or caches that use PHP serialization.

---

## Usage

### Defining an entity

Declare the class, implement `DataObjectInterface`, and compose all four traits. Properties must be declared explicitly — the constructor uses `get_object_vars()` to discover them, so undeclared dynamic properties are invisible.

```php
<?php declare(strict_types=1);

use Altair\Data\Contracts\DataObjectInterface;
use Altair\Data\Traits\DateAttributeMutatorAwareTrait;
use Altair\Data\Traits\ImmutableAttributesAwareTrait;
use Altair\Data\Traits\JsonSerializableAwareTrait;
use Altair\Data\Traits\SerializeAwareTrait;

final class UserProfile implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use DateAttributeMutatorAwareTrait;
    use SerializeAwareTrait;

    private ?int    $id         = null;
    private ?string $email      = null;
    private ?string $role       = null;
    private ?string $created_at = null;
    private ?string $updated_at = null;
}
```

Declare default values (`= null`, `= ''`, `= 0`) to ensure `toArray()` always includes every attribute, even when the caller omits it from the constructor array. This makes the serialized shape predictable.

### JSON serialization

`json_encode()` calls `jsonSerialize()` automatically. The method returns `toArray()`, so the JSON keys mirror your property names exactly.

```php
// Use json_encode directly — no extra step required.
$json = json_encode($profile);

// Decode back to verify round-trip fidelity.
$data = json_decode($json, true);
assert($data['email'] === $profile->email);
```

Nested entities serialize recursively. If a property holds another `ArrayableInterface` instance, `toArray()` calls `toArray()` on it before returning.

### Date attribute mutators

Pass the attribute name to read its value as a `Carbon\Carbon` instance or as a formatted date string. The trait constructs a `Carbon` object from the raw string each time you call the method — there is no caching.

```php
// Read as a Carbon instance for arithmetic and comparisons.
$createdAt = $profile->asCarbonDate('created_at');
$age = $createdAt->diffInDays(Carbon\Carbon::now());

// Read as a formatted string for display or storage.
$iso = $profile->asDateString('created_at', 'Y-m-d');         // '2026-01-15'
$rfc = $profile->asDateString('created_at');                   // RFC 2822 (default format 'r')
```

The `format` parameter in `asDateString()` accepts any format string accepted by PHP's `date()` function. The default is `'r'` (RFC 2822), which is useful for HTTP headers.

### Arrayable contract

`toArray()` returns all declared properties as an associative array. Values that implement `ArrayableInterface` are expanded recursively. All other values are returned as-is.

```php
$array = $profile->toArray();
// ['id' => 1, 'email' => 'alice@example.com', 'role' => 'admin',
//  'created_at' => '2026-01-15 09:00:00', 'updated_at' => null]
```

Because `toArray()` uses `get_object_vars($this)`, it returns only properties that are declared on the class. Static properties and properties of parent classes are not included unless they are visible from the `$this` scope.

### Producing a modified copy

`withData(array $data)` clones the entity and applies the given array to the clone. Keys not present in the entity's declared properties are silently dropped.

```php
// The original $profile is never modified.
$updated = $profile->withData(['role' => 'editor', 'updated_at' => '2026-05-01 12:00:00']);

assert($profile->role === 'admin');
assert($updated->role === 'editor');
assert($updated->email === $profile->email); // unchanged attributes are copied
```

### Checking and reading attributes

Use `has()` to check existence (by property declaration, not by non-null value) and `get()` to read safely.

```php
if ($profile->has('role')) {
    $role = $profile->get('role'); // throws InvalidArgumentException if key is absent
}

// __get() is sugar for get().
$role = $profile->role;
```

Note that `__isset()` returns `false` when the value is `null`, even if the property is declared. Use `has()` when you need to test for the property's existence regardless of its value.

---

## Configuration

There is no configuration. The package has no service providers, no config files, and no environment variables. Compose the traits, declare your properties, and you are done.

---

## Testing

Entities are straightforward to assert against because they carry no side effects and serialize predictably. Use constructor arrays to set up state and `assertEquals` / `assertSame` to verify output.

The following patterns cover the scenarios you are most likely to encounter:

```php
<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Altair\Data\Contracts\ArrayableInterface;
use Altair\Data\Contracts\DataObjectInterface;
use Carbon\Carbon;

final class UserProfileTest extends TestCase
{
    private array $data = [
        'id'         => 7,
        'email'      => 'alice@example.com',
        'role'       => 'admin',
        'created_at' => '2026-01-15 09:00:00',
        'updated_at' => null,
    ];

    private UserProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new UserProfile($this->data);
    }

    public function testImplementsContracts(): void
    {
        $this->assertInstanceOf(DataObjectInterface::class, $this->profile);
        $this->assertInstanceOf(ArrayableInterface::class, $this->profile);
        $this->assertInstanceOf(\JsonSerializable::class, $this->profile);
        $this->assertInstanceOf(\Serializable::class, $this->profile);
    }

    public function testAttributeAccess(): void
    {
        $this->assertSame(7, $this->profile->id);
        $this->assertSame('admin', $this->profile->get('role'));
        $this->assertTrue($this->profile->has('email'));
    }

    public function testToArrayMatchesInput(): void
    {
        $this->assertSame($this->data, $this->profile->toArray());
    }

    public function testJsonRoundTrip(): void
    {
        $json = json_encode($this->profile);
        $this->assertJson($json);
        $this->assertSame($this->data, json_decode($json, true));
    }

    public function testDateMutator(): void
    {
        $carbon = $this->profile->asCarbonDate('created_at');
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertSame('2026-01-15', $carbon->format('Y-m-d'));
    }

    public function testWithDataProducesNewInstance(): void
    {
        $updated = $this->profile->withData(['role' => 'editor']);

        $this->assertNotSame($this->profile, $updated);
        $this->assertSame('admin', $this->profile->role);
        $this->assertSame('editor', $updated->role);
        $this->assertSame($this->profile->email, $updated->email);
    }

    public function testSerializeRoundTrip(): void
    {
        $frozen = serialize($this->profile);
        $thawed = unserialize($frozen);

        $this->assertInstanceOf($this->profile::class, $thawed);
        $this->assertNotSame($this->profile, $thawed);
        $this->assertSame($this->data, $thawed->toArray());
    }

    public function testImmutabilityIsEnforced(): void
    {
        $this->expectException(\Altair\Data\Exception\RuntimeException::class);
        $this->profile->role = 'guest'; // triggers __set(), which throws
    }
}
```

---

## Extending

### Custom mutators beyond dates

The trait system is open. You can add a trait that operates on the same private properties by declaring it alongside the four standard traits. Your custom trait can call `get(string $key)` (provided by `AttributesAwareTrait` through `ImmutableAttributesAwareTrait`) to read values without breaking encapsulation.

```php
<?php declare(strict_types=1);

trait MoneyAttributeMutatorTrait
{
    // Requires AttributesAwareTrait to already be composed.
    public function asMoneyAmount(string $key, string $currency = 'USD'): string
    {
        $value = $this->get($key);
        return number_format((float) $value, 2) . ' ' . $currency;
    }
}
```

Then compose it in your entity:

```php
final class Invoice implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use DateAttributeMutatorAwareTrait;
    use SerializeAwareTrait;
    use MoneyAttributeMutatorTrait;

    private ?int    $id           = null;
    private ?string $total_amount = null;
    private ?string $issued_at    = null;
}
```

### Pairing with a persistence layer

The Data package owns no repository contracts. When you need to load and store Data objects, depend on the `univeros/persistence` package: its `RepositoryInterface` is the framework's single repository abstraction. A persistence adapter fetches a row and hydrates a Data object — for example by passing the row array straight to the constructor — so the Data object stays free of any storage concern.

```php
<?php declare(strict_types=1);

// In your persistence layer (univeros/persistence), not in Data:
$row = $connection->fetchRow('SELECT * FROM users WHERE id = ?', [$id]);

return $row === null ? null : new UserProfile($row);
```

---

## Recipes

### Money attribute mutator

Store monetary values as strings (to avoid floating-point rounding) and expose a formatted read accessor.

```php
trait MoneyAttributeMutatorTrait
{
    public function asFormattedMoney(string $key, int $decimals = 2, string $symbol = '$'): string
    {
        return $symbol . number_format((float) $this->get($key), $decimals);
    }
}

final class OrderLine implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use SerializeAwareTrait;
    use MoneyAttributeMutatorTrait;

    private ?string $unit_price = null;
    private ?int    $quantity   = null;
}

$line = new OrderLine(['unit_price' => '19.99', 'quantity' => 3]);
echo $line->asFormattedMoney('unit_price'); // '$19.99'
```

### JSON column attribute

Store a JSON blob in one attribute and expose it as a decoded array at read time.

```php
trait JsonColumnMutatorTrait
{
    public function asDecodedJson(string $key): array
    {
        $raw = $this->get($key);
        return is_string($raw) ? (json_decode($raw, true) ?? []) : [];
    }
}

final class EventLog implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use SerializeAwareTrait;
    use JsonColumnMutatorTrait;

    private ?string $payload = null;
}

$log = new EventLog(['payload' => '{"action":"login","ip":"127.0.0.1"}']);
$decoded = $log->asDecodedJson('payload'); // ['action' => 'login', 'ip' => '127.0.0.1']
```

### Default attribute values

Declare property defaults at the class level so `toArray()` always includes every key, even when the caller omits the value.

```php
final class Notification implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use SerializeAwareTrait;

    private string $type     = 'info';
    private string $message  = '';
    private bool   $read     = false;
}

// Missing keys fall back to the declared defaults.
$n = new Notification(['message' => 'Your export is ready.']);
assert($n->type === 'info');
assert($n->read === false);
```

### Change detection

Because `withData()` returns a new clone, you can diff two instances by comparing their `toArray()` outputs.

```php
function changedKeys(DataObjectInterface $before, DataObjectInterface $after): array
{
    $prev = $before->toArray();
    $next = $after->toArray();

    return array_keys(array_filter(
        $next,
        static fn($v, string $k) => $v !== $prev[$k],
        ARRAY_FILTER_USE_BOTH
    ));
}

$original = new UserProfile(['id' => 1, 'email' => 'a@b.com', 'role' => 'viewer']);
$modified = $original->withData(['role' => 'editor']);

$changed = changedKeys($original, $modified); // ['role']
```

### Producing a partial entity

`withData()` ignores keys that are not declared as properties. You can use this to hydrate an entity from a database row that returns only a subset of columns.

```php
// Storage layer fetches only id and email for a list view.
$row = ['id' => 5, 'email' => 'bob@example.com'];

// Extra keys (none here) would be dropped; missing declared keys stay at their defaults.
$partial = new UserProfile($row);

echo $partial->id;    // 5
echo $partial->role;  // null (default)
```

---

## Related packages

- [**validation.md**](./validation.md) — The Validation package operates on arrays and entities. Pass `$entity->toArray()` to a validator to check field-level rules before constructing or updating an entity.
- [**common.md**](./common.md) — The Common package provides array helpers (`Altair\Common\Helper\ArrHelper`) that complement `toArray()` for transformation, filtering, and flattening use cases.

---

## Limitations

These are deliberate scope boundaries, not gaps. Data is the value-object layer; persistence, relations, and coercion belong to `univeros/persistence`, which may depend on Data to hydrate or expose Data objects.

- **No persistence.** The package does not connect to any database, file system, or external service. Data objects are pure in-memory value objects.
- **No relations.** There is no mechanism for lazy-loading or eager-loading associated objects. If you need to nest objects, set a composed object as a property value before construction, or use a custom mutator trait to decode a related payload.
- **No ORM features.** There is no identity map, unit-of-work, dirty-tracking, or schema reflection, and the package ships no repository contracts. Use `univeros/persistence` and its `RepositoryInterface` when you need them.
- **No automatic type coercion.** The constructor assigns values as-is. If your storage layer returns integers as strings, you must coerce them before passing to the constructor or in a custom mutator trait.
- **`serialize()` / `unserialize()` writes directly to properties.** The `unserialize()` implementation in `SerializeAwareTrait` bypasses `__set()` by writing to `$this->{$key}` inside the trait's own scope. This is intentional — it is the only path for deserializing an immutable object — but it means a deserialized object does not pass through the constructor or any validation logic.
