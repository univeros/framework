# univeros/session  ·  Altair\Session

**Purpose:** Server-side session storage with pluggable handlers for the filesystem, MongoDB, PDO databases (MySQL/PostgreSQL/SQLite), and Redis.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `CsrfTokenInterface` | `generateValue()` | `string` |  |
|  | `getValue()` | `string` |  |
|  | `isValid(string)` | `bool` |  |
| `PdoSessionAdapterInterface` | `beginTransaction()` | `void` | constants: `DRIVER_MYSQL`, `DRIVER_POSTGRESQL`, `DRIVER_SQLITE`, `LOCK_ADVISORY`, `LOCK_NONE`, `LOCK_TRANSACTIONAL` |
|  | `close(bool)` | `bool` |  |
|  | `commit()` | `void` |  |
|  | `connect()` | `void` |  |
|  | `delete(string)` | `bool` |  |
|  | `doAdvisoryLocking(string)` | `PDOStatement` |  |
|  | `getConnection()` | `PDO` |  |
|  | `getDriver()` | `string` |  |
|  | `getHasSessionExpired()` | `bool` |  |
|  | `getIsConnected()` | `bool` |  |
|  | `getMergePdoStatement(string, string)` | `PDOStatement\|null` |  |
|  | `getSelectSql()` | `string` |  |
|  | `read(string)` | `string` |  |
|  | `rollback()` | `void` |  |
|  | `write(string, string)` | `bool` |  |
| `PdoSessionHandlerInterface` | `getHasSessionExpired()` | `bool` | extends `SessionHandlerInterface` |
| `SessionBlockInterface` | `appendFlash(string, mixed, bool)` | `SessionBlockInterface` | constants: `CSRF_KEY`, `FLASH_KEY` |
|  | `clear()` | `SessionBlockInterface` |  |
|  | `get(string, mixed)` | `mixed` |  |
|  | `getAllFlashes(mixed)` | `mixed` |  |
|  | `getFlash(string, mixed, bool)` | `mixed` |  |
|  | `has(string)` | `bool` |  |
|  | `hasFlash(mixed)` | `bool` |  |
|  | `remove(string)` | `SessionBlockInterface` |  |
|  | `removeAllFlashes()` | `void` |  |
|  | `removeFlash(mixed)` | `mixed` |  |
|  | `set(string, mixed)` | `SessionBlockInterface` |  |
|  | `setFlash(string, mixed, bool)` | `SessionBlockInterface` |  |
| `SessionManagerInterface` | `clear()` | `void` |  |
|  | `close()` | `void` |  |
|  | `destroy()` | `bool` |  |
|  | `exists()` | `bool` |  |
|  | `getCookieParams()` | `array` |  |
|  | `getCsrfToken()` | `CsrfTokenInterface` |  |
|  | `getId()` | `string` |  |
|  | `getIsActive()` | `bool` |  |
|  | `getName()` | `string` |  |
|  | `getSavePath()` | `string` |  |
|  | `getSessionBlock(string)` | `SessionBlockInterface` |  |
|  | `regenerateId(bool)` | `bool` |  |
|  | `resume()` | `bool` |  |
|  | `setCookieParams(array)` | `void` |  |
|  | `setDeleteCookieCallable(callable\|null)` | `void` |  |
|  | `setId(string)` | `void` |  |
|  | `setName(string)` | `void` |  |
|  | `setSavePath(string)` | `void` |  |
|  | `start()` | `bool` |  |

## Concrete classes

- `CsrfToken` — implements `CsrfTokenInterface`
- `FileSessionHandler` — implements `SessionHandlerInterface`
- `FileSessionHandlerConfiguration` — implements `ConfigurationInterface`
- `MongoSessionHandler` — implements `SessionHandlerInterface`
- `MongoSessionHandlerConfiguration` — implements `ConfigurationInterface`
- `MySqlPdoSessionAdapter` — implements `PdoSessionAdapterInterface`
- `MySqlSessionHandlerConfiguration` — implements `ConfigurationInterface`
- `PdoSessionHandler` — implements `PdoSessionHandlerInterface`, `SessionHandlerInterface`
- `PostgreSqlPdoSessionAdapter` — implements `PdoSessionAdapterInterface`
- `PostgreSqlSessionHandlerConfiguration` — implements `ConfigurationInterface`
- `PredisSessionHandler` — implements `SessionHandlerInterface`
- `PredisSessionHandlerConfiguration` — implements `ConfigurationInterface`
- `SessionBlock` — implements `SessionBlockInterface`
- `SessionBlockFactory`
- `SessionManager` — implements `SessionManagerInterface`
- `SessionManagerConfiguration` — implements `ConfigurationInterface`
- `SqlitePdoSessionAdapter` — implements `PdoSessionAdapterInterface`
- `SqliteSessionHandlerConfiguration` — implements `ConfigurationInterface`

## Tests as documentation

- `tests/Session/CsrfTokenTest.php`
- `tests/Session/FileSessionHandlerTest.php`
- `tests/Session/MongoSessionHandlerTest.php`
- `tests/Session/PdoSessionHandlerTest.php`
- `tests/Session/SessionBlockTest.php`
- `tests/Session/SessionManagerTest.php`

## Related packages

- `nesbot/carbon`
- `univeros/configuration`
- `univeros/security`
