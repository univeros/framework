# univeros/cookie  ·  Altair\Cookie

**Purpose:** Immutable value objects for HTTP cookies, plus a manager that reads, writes, and modifies them on PSR-7 requests and responses.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `CookieInterface` | `getName()` | `string` | constants: `HEADER` |
|  | `getValue()` | `string\|null` |  |
| `SetCookieInterface` | `getDomain()` | `string\|null` | constants: `HEADER` |
|  | `getExpires()` | `int` |  |
|  | `getHttpOnly()` | `bool` |  |
|  | `getMaxAge()` | `int` |  |
|  | `getName()` | `string` |  |
|  | `getPath()` | `string\|null` |  |
|  | `getSecure()` | `bool` |  |
|  | `getValue()` | `string\|null` |  |

## Concrete classes

- `AbstractCookie` _(abstract)_
- `Cookie` _(final)_ — implements `CookieInterface`, `Stringable`
- `CookieCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `CookieFactory`
- `CookieManager`
- `CookieStr`
- `SetCookie` _(final)_ — implements `SetCookieInterface`, `Stringable`
- `SetCookieCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `SetCookieFactory`

## Tests as documentation

- `tests/Cookie/CookieFactoryTest.php`
- `tests/Cookie/CookieManagerTest.php`
- `tests/Cookie/CookieStrTest.php`
- `tests/Cookie/CookieTest.php`
- `tests/Cookie/SetCookieFactoryTest.php`
- `tests/Cookie/SetCookieTest.php`

## Related packages

- `psr/http-message`
- `univeros/structure`
