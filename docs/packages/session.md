# Session

Server-side session storage with pluggable handlers for the filesystem, MongoDB, PDO-backed databases (MySQL, PostgreSQL, SQLite), and Redis via Predis.

**Package:** `univeros/session`
**Namespace:** `Altair\Session`

---

## Introduction

The Session package separates session storage from the cookie envelope that carries the session ID over HTTP. The cookie wire format — `Set-Cookie` headers, `Secure`, `HttpOnly`, `SameSite` — is handled by `./cookie.md`. This package handles everything that happens once PHP has the session ID in hand: reading and writing session data through a `SessionHandlerInterface` implementation, managing session lifecycle (start, resume, close, destroy), and regenerating IDs to prevent fixation.

PHP's native session machinery (`session_start`, `session_write_close`, `$_SESSION`) remains the runtime. What changes is the storage backend. When you register a custom `SessionHandlerInterface` with `session_set_save_handler()`, PHP delegates every read and write to your handler instead of writing files in the default `session.save_path`. `SessionManager::start()` calls `session_set_save_handler($this->sessionHandler, false)` before `session_start()`, so your handler is active for the lifetime of the request.

The package organises its functionality into three layers. The handler layer (`Handler/`) contains the four concrete `SessionHandlerInterface` implementations. The adapter layer (`Adapter/`) sits underneath `PdoSessionHandler` and handles the SQL dialect differences between MySQL, PostgreSQL, and SQLite — including the different locking strategies each database supports. The manager layer (`SessionManager`) is the single entry point for application code: it starts sessions, provides namespaced sub-arrays through `SessionBlock`, manages the CSRF token, and registers a shutdown function that calls `session_write_close()` so session data is always flushed even if application code forgets to call `close()`.

Session data is partitioned into named `SessionBlock` instances. Rather than reading from and writing to `$_SESSION` directly, you call `$manager->getSessionBlock('myapp')` to get a scoped view that reads and writes only to `$_SESSION['myapp']`. Blocks also carry flash messages — values that persist for exactly one more request and are then removed automatically.

The package depends on `univeros/security` for the `Salt` helper used in CSRF token generation, and on `nesbot/carbon` for the expiry check in `FileSessionHandler`. Storage-specific extensions (ext-mongodb, ext-pdo, predis/predis) are optional at install time but required at runtime if you activate the corresponding handler.

---

## Installation

Install the package with Composer:

```bash
composer require univeros/session
```

The package requires **PHP 8.3 or later**. Depending on which handler you intend to use, add the appropriate dependency:

```bash
# File handler — no additional dependency (Altair\Filesystem is pulled in transitively)

# MongoDB handler
# Requires the mongodb PECL extension:
# pecl install mongodb
# and the mongodb/mongodb library if you want the typed Collection object:
composer require mongodb/mongodb

# PDO handler (MySQL, PostgreSQL, or SQLite)
# ext-pdo is bundled with PHP; ensure you have the driver-specific extension enabled:
# - ext-pdo_mysql    for MySQL / MariaDB
# - ext-pdo_pgsql   for PostgreSQL
# - ext-pdo_sqlite  for SQLite

# Predis handler (Redis, pure PHP)
composer require predis/predis
```

If you are using the full `univeros/framework` meta-package, the session package is already included.

---

## Quick start

The following example shows the minimum code to start a file-backed session and read and write a value. Use `FileSessionHandler` during development; it requires no external services.

```php
use Altair\Filesystem\Filesystem;
use Altair\Session\Handler\FileSessionHandler;
use Altair\Session\SessionManager;
use Psr\Http\Message\ServerRequestInterface;

// $request is a PSR-7 ServerRequestInterface, typically provided by your
// HTTP layer (e.g. the Altair Http package or Laminas Diactoros).
$handler = new FileSessionHandler(
    filesystem: new Filesystem(),
    path:       '/var/sessions',  // must exist and be writable
    minutes:    120,              // session lifetime in minutes
);

$manager = new SessionManager($request, $handler);
$manager->start();

// Obtain a namespaced block so your keys do not collide with other packages.
$cart = $manager->getSessionBlock('store.cart');

// Write a value.
$cart->set('item_count', 3);

// Read it back — returns null if the key is absent.
$count = $cart->get('item_count'); // 3

// Flush and close.
$manager->close();
```

---

## Concepts

### SessionManager

`SessionManager` is the orchestration entry point for the entire package. It accepts a PSR-7 `ServerRequestInterface` in its constructor so that it can read the incoming cookies without touching the `$_COOKIE` superglobal directly. You pass an optional `SessionHandlerInterface` implementation; when none is provided, PHP's default file handler is used.

On construction, `SessionManager` registers a `shutdown_function` that calls `close()`. This means `session_write_close()` will be called when the PHP process ends, even if your application exits without explicitly calling `$manager->close()`. This is a safety net, not a substitute for explicit lifecycle management.

The interface `SessionManagerInterface` contracts all the session lifecycle methods: `start`, `resume`, `close`, `destroy`, `clear`, `regenerateId`, plus cookie parameter management and CSRF token access. Application code should depend on this interface rather than the concrete class.

### SessionHandlerInterface implementations

PHP defines `SessionHandlerInterface` with six methods: `open`, `close`, `read`, `write`, `destroy`, and `gc`. Every handler in this package implements this interface.

| Class | Backend | Key dependency |
|---|---|---|
| `FileSessionHandler` | Local filesystem | `Altair\Filesystem` |
| `MongoSessionHandler` | MongoDB collection | `ext-mongodb` |
| `PdoSessionHandler` | MySQL / PostgreSQL / SQLite | `ext-pdo` + adapter |
| `PredisSessionHandler` | Redis | `predis/predis` |

`PdoSessionHandler` is the only handler that delegates to a database-specific adapter class (`MySqlPdoSessionAdapter`, `PostgreSqlPdoSessionAdapter`, `SqlitePdoSessionAdapter`). Each adapter implements the SQL dialect and locking strategy for its target DBMS while sharing a common `PdoSessionAdapterAwareTrait` that contains the connection management and read/write logic.

### SessionBlock

`SessionBlock` is the namespaced view into `$_SESSION`. When you call `$manager->getSessionBlock('app.user')`, you get a `SessionBlock` bound to the `'app.user'` key in `$_SESSION`. All reads and writes go through `$_SESSION['app.user']`, so different components of your application can hold their own sub-array without naming collisions.

`SessionBlock` also manages flash messages. A flash value persists until it is read and then is deleted on the next request. The lifecycle is maintained by a counter stored under the `altair:session:flash` key within the block. When a `SessionBlock` is constructed it calls `updateFlashCounters()`, which increments or clears counters from the previous request.

### Cache limiters (via the Http package)

When PHP starts a session, its default behaviour is to emit `Cache-Control`, `Expires`, and `Pragma` headers that tell browsers and proxies not to cache the response. This is the "nocache" mode, controlled by `session.cache_limiter` in `php.ini`.

Because the Altair Session package does not control HTTP response headers directly, cache limiter behaviour is implemented in the `Altair\Http` package via `SessionHeadersMiddleware` and the `CacheLimiterInterface` hierarchy. The `Http` package provides four concrete limiters you can pass to `SessionHeadersMiddleware`:

| Class | `Cache-Control` emitted |
|---|---|
| `NoCacheLimiter` | `no-store, no-cache, must-revalidate, post-check=0, pre-check=0` |
| `PrivateCacheLimiter` | `private, max-age=N, pre-check=N` + hard `Expires: [past date]` |
| `PrivateNoExpireCacheLimiter` | `private, max-age=N, pre-check=N` (no `Expires` header) |
| `PublicCacheLimiter` | `public, max-age=N` + a future `Expires` |

The `cacheExpire` constructor argument on all limiters defaults to 180 minutes and corresponds to the `session.cache_expire` INI setting. You select one of these classes when you wire `SessionHeadersMiddleware` — this is how you replace PHP's native header-emission behaviour with explicit PSR-7 immutable response mutations. See [http.md](./http.md) for middleware wiring details.

---

## Usage

### File handler

The file handler stores each session as a flat file named by the session ID in a directory you specify. Reads check the file's mtime; if the file is older than `$minutes` minutes, the handler returns an empty string so PHP treats the session as expired.

```php
use Altair\Filesystem\Filesystem;
use Altair\Session\Handler\FileSessionHandler;

// The directory must exist. SessionManager::setSavePath() validates this
// if you need to set it at runtime.
$handler = new FileSessionHandler(
    filesystem: new Filesystem(),
    path:       '/var/sessions',
    minutes:    120,
);
```

Garbage collection (`gc()`) iterates all files in the directory and deletes any file whose `filemtime` plus `$maxlifetime` seconds is in the past. PHP triggers `gc()` probabilistically on `session_start()`; the `$minutes` constructor argument and PHP's `session.gc_maxlifetime` INI value are separate controls — the handler honours both.

### Mongo handler

The Mongo handler persists sessions as documents in a MongoDB collection. Each document has the structure:

```
{
    _id:              "<session-id>",
    content:          Binary("<serialised session data>"),
    session_lifetime: UTCDateTime("<expiry timestamp>"),
    session_time:     UTCDateTime("<write timestamp>")
}
```

You pass a fully configured `MongoDB\Collection` instance to the constructor. The collection is opened before the handler is used, so you control the MongoDB client, database, and collection names directly.

```php
use Altair\Session\Handler\MongoSessionHandler;
use MongoDB\Client;

// Reads filter on session_lifetime >= now, so expired documents are
// not returned even before gc() removes them.
$collection = (new Client('mongodb://localhost:27017'))
    ->selectCollection('myapp', 'sessions');

$handler = new MongoSessionHandler($collection);
```

`write()` uses `updateOne` with `upsert: true`, so the first write for a new session ID inserts a document and subsequent writes update it. `gc()` calls `deleteMany` with a `session_lifetime < now` filter. Both operations catch `MongoDB\Driver\Exception\Exception` and return `false` on failure rather than letting the exception propagate through PHP's session machinery.

To create a TTL index so MongoDB handles expiry automatically at the server level, run:

```javascript
db.sessions.createIndex({ session_lifetime: 1 }, { expireAfterSeconds: 0 })
```

This removes expired documents in the background and complements (but does not replace) the `gc()` call.

### PDO handler

`PdoSessionHandler` delegates to one of three adapter classes depending on your target database. You construct the adapter directly and pass it to the handler.

#### MySQL

Create the sessions table before registering the handler:

```sql
CREATE TABLE sessions (
    id             VARBINARY(128) NOT NULL PRIMARY KEY,
    content        BLOB NOT NULL,
    session_lifetime MEDIUMINT NOT NULL,
    session_time   INTEGER UNSIGNED NOT NULL
) COLLATE utf8_bin ENGINE = InnoDB;
```

```php
use Altair\Session\Adapter\MySqlPdoSessionAdapter;
use Altair\Session\Handler\PdoSessionHandler;
use Altair\Session\Contracts\PdoSessionAdapterInterface;

$adapter = new MySqlPdoSessionAdapter(
    dsn:      'mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4',
    username: 'dbuser',
    password: 'secret',
    table:    'sessions',
    lockMode: PdoSessionAdapterInterface::LOCK_TRANSACTIONAL,
);

$handler = new PdoSessionHandler($adapter);
```

MySQL uses `INSERT ... ON DUPLICATE KEY UPDATE` for upserts. In `LOCK_TRANSACTIONAL` mode the adapter issues `SET TRANSACTION ISOLATION LEVEL READ COMMITTED` before beginning the transaction, which avoids the gap-lock deadlocks that MySQL's default `REPEATABLE READ` isolation can produce for concurrent sessions.

#### PostgreSQL

```sql
CREATE TABLE sessions (
    id               VARCHAR(128) NOT NULL PRIMARY KEY,
    content          BYTEA NOT NULL,
    session_lifetime INTEGER NOT NULL,
    session_time     INTEGER NOT NULL
);
```

```php
use Altair\Session\Adapter\PostgreSqlPdoSessionAdapter;
use Altair\Session\Handler\PdoSessionHandler;

$adapter = new PostgreSqlPdoSessionAdapter(
    dsn:      'pgsql:host=127.0.0.1;dbname=myapp',
    username: 'dbuser',
    password: 'secret',
    table:    'sessions',
);

$handler = new PdoSessionHandler($adapter);
```

For PostgreSQL 9.5 or later, the adapter uses `INSERT ... ON CONFLICT (id) DO UPDATE` for upserts. For older versions it falls back to a separate UPDATE followed by INSERT, with a retry on SQLSTATE 23 (integrity violation) to handle the race condition where two requests write the same session simultaneously.

Advisory locking on PostgreSQL converts the hex session ID to a 64-bit integer (or two 32-bit integers on 32-bit PHP) and calls `pg_advisory_lock()`. The release statement is queued in `$unlockStatements` and executed when `close()` runs.

#### SQLite

```sql
CREATE TABLE sessions (
    id               TEXT NOT NULL PRIMARY KEY,
    content          BLOB NOT NULL,
    session_lifetime INTEGER NOT NULL,
    session_time     INTEGER NOT NULL
);
```

```php
use Altair\Session\Adapter\SqlitePdoSessionAdapter;
use Altair\Session\Handler\PdoSessionHandler;

$adapter = new SqlitePdoSessionAdapter(
    dsn:   'sqlite:/var/db/sessions.db',
    username: '',
    password: '',
    table: 'sessions',
);

$handler = new PdoSessionHandler($adapter);
```

SQLite uses `INSERT OR REPLACE` for upserts. Advisory locking is not supported and throws `Error` if you attempt to set `LOCK_ADVISORY`. In transactional mode, the adapter issues `BEGIN IMMEDIATE TRANSACTION` directly via `exec()` because SQLite does not support row-level locks; the immediate mode acquires a write lock on the database file before the read, which is the only way to prevent write conflicts on SQLite.

#### Lock modes

All PDO adapters accept a `$lockMode` argument. The three modes are defined as constants on `PdoSessionAdapterInterface`:

| Constant | Value | Behaviour |
|---|---|---|
| `LOCK_NONE` | `0` | No locking. Concurrent writes to the same session may overwrite each other. Use only when you implement your own optimistic concurrency. |
| `LOCK_ADVISORY` | `1` | Application-level advisory lock (MySQL `GET_LOCK`, PostgreSQL `pg_advisory_lock`). Not enforced by the database row; not available on SQLite. |
| `LOCK_TRANSACTIONAL` | `2` | Real row-level lock within a transaction. Default. The only mode that is reliable across all supported databases. |

GC is deliberately deferred to `close()` by the handler. The `gc()` method simply sets a flag (`$gcCalled = true`) and returns `true`. When `close()` runs, it commits the transaction and releases advisory locks first, then — if `$gcCalled` is true — executes the `DELETE FROM sessions WHERE (session_lifetime + session_time) < :now` cleanup query. This prevents expired-session pruning from holding the lock while the current session is still active.

### Predis handler

`PredisSessionHandler` extends `Predis\Session\Handler` from the `predis/predis` library, which already implements `SessionHandlerInterface`. The class body is intentionally minimal — it exists to give the handler a namespaced class name within `Altair\Session` and to act as the container alias target.

```php
use Altair\Session\Handler\PredisSessionHandler;
use Predis\Client;

// The Predis client handles connection, serialisation, and TTL via Redis SETEX.
$client = new Client([
    'host'   => '127.0.0.1',
    'port'   => 6379,
    'scheme' => 'tcp',
]);

// PredisSessionHandler accepts the client via its parent constructor.
// The parent class also accepts an options array as the second argument,
// which includes 'gc_maxlifetime' and 'prefix'.
$handler = new PredisSessionHandler($client, [
    'gc_maxlifetime' => 7200,
    'prefix'         => 'session:',
]);
```

Since the session lifetime and key prefix are controlled by `Predis\Session\Handler`, you configure them in the options array rather than via INI settings. The `gc()` method in the Predis handler is a no-op because Redis expires keys automatically via TTL — no explicit garbage collection is needed.

### Cache limiters

Cache limiters live in the `Altair\Http` package and are applied by `SessionHeadersMiddleware`. You select the appropriate limiter when wiring the middleware.

Choose `NoCacheLimiter` when session responses must not be stored anywhere (the typical default for authenticated pages):

```php
use Altair\Http\Middleware\SessionHeadersMiddleware;
use Altair\Http\Support\NoCacheLimiter;

$middleware = new SessionHeadersMiddleware($cookieManager, new NoCacheLimiter());
```

Choose `PublicCacheLimiter` when pages associated with a session can be cached by shared proxies (for example, a personalisation banner that reads but does not write session state):

```php
use Altair\Http\Support\PublicCacheLimiter;

// 180 minutes is the default; pass an integer to override.
$middleware = new SessionHeadersMiddleware($cookieManager, new PublicCacheLimiter(60));
```

`PrivateCacheLimiter` adds a hard `Expires: [past date]` before delegating to `PrivateNoExpireCacheLimiter`. The hard past-date `Expires` header instructs HTTP/1.0 proxies to treat the response as uncacheable, while the `Cache-Control: private` header handles HTTP/1.1 clients. Use `PrivateNoExpireCacheLimiter` directly when you target HTTP/1.1-only infrastructure and do not want the redundant `Expires` header.

### SessionManager — lifecycle

Every application request that involves session data follows this sequence:

```php
// 1. Construct the manager once per request.
$manager = new SessionManager($request, $handler);

// 2a. Start a brand-new session, or resume an existing one.
$manager->start();

// 2b. Alternatively, resume only if a session cookie is already present.
//     This avoids creating a new session for unauthenticated requests.
if (!$manager->resume()) {
    // No existing session — redirect to login, for example.
}

// 3. Do work via session blocks.
$block = $manager->getSessionBlock('app');

// 4. Close at the end of the request.
//    The shutdown function registered in the constructor is a fallback only.
$manager->close();
```

`start()` is idempotent: if the session is already active (`session_status() === PHP_SESSION_ACTIVE`), it returns `true` immediately without calling `session_start()` again.

`destroy()` starts the session if it is not already active, calls `session_unset()`, then `session_destroy()`, and finally invokes the delete-cookie callable. The default callable emits a `Set-Cookie` header with `expires` set 42000 seconds in the past, which causes browsers to delete the session cookie immediately.

### Reading and writing session data

Read and write all session data through `SessionBlock` rather than touching `$_SESSION` directly. This keeps your keys namespaced and makes blocks testable with a mocked `SessionManager`.

```php
$user = $manager->getSessionBlock('auth.user');

// Write
$user->set('id', 42);
$user->set('roles', ['editor', 'admin']);

// Read — returns null if missing, or the $default you provide
$id = $user->get('id');                   // 42
$name = $user->get('name', 'Anonymous');  // 'Anonymous'

// Check presence
if ($user->has('roles')) {
    // ...
}

// Remove a single key
$user->remove('roles');

// Clear all keys in this block
$user->clear();
```

### Flash messages

Flash messages persist for exactly one additional request. They are useful for displaying a confirmation or error after a redirect.

```php
$notices = $manager->getSessionBlock('notices');

// Set a flash on request A:
$notices->setFlash('success', 'Your profile was updated.');

// On request B (the next request), read it:
$message = $notices->getFlash('success'); // 'Your profile was updated.'

// The counter now marks it for deletion. On request C it is gone.
$notices->getFlash('success'); // null
```

Pass `$delete = true` to `getFlash()` to delete the message in the same request rather than the next:

```php
$notices->getFlash('success', null, true); // deleted immediately
```

Append multiple values under the same key with `appendFlash()`. The value is stored as an array:

```php
$notices->appendFlash('errors', 'Email is required.');
$notices->appendFlash('errors', 'Password is too short.');
// $notices->getFlash('errors') → ['Email is required.', 'Password is too short.']
```

### Regenerating session IDs

Regenerate the session ID whenever a user's privilege level changes — most importantly on successful login — to prevent session fixation attacks. When the session has a CSRF token active, `regenerateId()` generates a new CSRF value automatically.

```php
// Called after verifying the user's credentials:
if ($manager->regenerateId(deletePrevious: true)) {
    // The old session file (or record) has been deleted and a new ID issued.
    // The session data carried over to the new ID.
}
```

Pass `$deletePrevious = false` to keep the old session record alive while the new one is created. This is rarely useful and creates a window where both IDs are valid. Prefer the default of `true`.

`regenerateId()` has no effect if the session is not active — it returns `false` and does not throw. Call `start()` before `regenerateId()`.

---

## Configuration

Each handler has a corresponding `ConfigurationInterface` class in `Altair\Session\Configuration\` that wires the handler into an `Altair\Container\Container` via environment variables.

### FileSessionHandlerConfiguration

```php
use Altair\Session\Configuration\FileSessionHandlerConfiguration;

(new FileSessionHandlerConfiguration())->apply($container);
```

Environment variables consumed:

| Variable | Description |
|---|---|
| `SESSION_FILE_PATH` | Filesystem path where session files are stored |
| `SESSION_FILE_MINUTES` | Session file lifetime in minutes |

After `apply()`, `SessionHandlerInterface` resolves to `FileSessionHandler` in the container.

### MongoSessionHandlerConfiguration

```php
use Altair\Session\Configuration\MongoSessionHandlerConfiguration;

(new MongoSessionHandlerConfiguration())->apply($container);
```

Environment variables consumed:

| Variable | Default | Description |
|---|---|---|
| `SESSION_MONGO_URI` | `mongodb://12.0.0.1/` | MongoDB connection URI |
| `SESSION_MONGO_DB` | `session_db` | Database name |
| `SESSION_MONGO_COLLECTION` | `session_collection` | Collection name |

The configuration uses a delegate factory that builds a `MongoDB\Client`, selects the collection, and passes it to `MongoSessionHandler`. After `apply()`, `SessionHandlerInterface` resolves to `MongoSessionHandler`.

Note: the default `SESSION_MONGO_URI` value (`mongodb://12.0.0.1/`) is likely a placeholder — set this variable explicitly in your environment.

### MySqlSessionHandlerConfiguration, PostgreSqlSessionHandlerConfiguration, SqliteSessionHandlerConfiguration

All three PDO configurations share the same environment variables via `PdoAdapterDefinitionAwareTrait`:

```php
use Altair\Session\Configuration\MySqlSessionHandlerConfiguration;
// or PostgreSqlSessionHandlerConfiguration / SqliteSessionHandlerConfiguration

(new MySqlSessionHandlerConfiguration())->apply($container);
```

Environment variables consumed:

| Variable | Description |
|---|---|
| `SESSION_PDO_DSN` | PDO DSN string (e.g. `mysql:host=127.0.0.1;dbname=myapp`) |
| `SESSION_PDO_USERNAME` | Database username |
| `SESSION_PDO_PASSWORD` | Database password |
| `SESSION_PDO_TABLE` | Session table name |
| `SESSION_LOCK_MODE` | Lock mode integer; defaults to `LOCK_TRANSACTIONAL` (2) |

After `apply()`, `PdoSessionAdapterInterface` resolves to the appropriate adapter class and `SessionHandlerInterface` resolves to `PdoSessionHandler`.

### PredisSessionHandlerConfiguration

```php
use Altair\Session\Configuration\PredisSessionHandlerConfiguration;

(new PredisSessionHandlerConfiguration())->apply($container);
```

Environment variables consumed:

| Variable | Default | Description |
|---|---|---|
| `SESSION_REDIS_URI` | `tcp://127.0.0.1:6379` | Predis connection URI |

The configuration wires `Predis\Client` with the URI as its `parameters` argument and aliases it to `Predis\ClientInterface`. `SessionHandlerInterface` is aliased to `PredisSessionHandler`. For more advanced connection options (authentication, TLS, sentinel, cluster), instantiate the handler manually and pass a fully configured `Predis\Client`.

### SessionManagerConfiguration

```php
use Altair\Session\Configuration\SessionManagerConfiguration;

(new SessionManagerConfiguration())->apply($container);
```

This configuration only aliases `SessionManagerInterface` to `SessionManager`. It expects that `Psr\Http\Message\ServerRequestInterface` is already registered in the container (the `Http` package's `HttpMessageConfiguration` does this). Apply one of the handler configurations before this one, or register `SessionHandlerInterface` yourself.

---

## Testing

The test suite does not include an in-memory session handler. The closest equivalent for unit tests is to mock `SessionManager` so that `SessionBlock` operates against a real `$_SESSION` array without touching the filesystem or a database.

The pattern used in `tests/Session/SessionBlockTest.php` shows how to do this:

```php
use Altair\Session\SessionBlock;
use Altair\Session\SessionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MyFeatureTest extends TestCase
{
    protected function getSessionBlock(string $name = 'test'): SessionBlock
    {
        $manager = $this->getMockBuilder(SessionManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $manager->method('start')->willReturn(true);

        return new SessionBlock($name, $manager);
    }

    public function testSomethingThatUsesABlock(): void
    {
        $block = $this->getSessionBlock('my.feature');
        $block->set('key', 'value');
        $this->assertSame('value', $block->get('key'));
    }
}
```

`SessionBlock`'s constructor calls `resumeOrStartSession()`, which calls `$manager->resume()` and, if that returns false, `$manager->start()`. Mocking `start()` to return `true` is sufficient to let the block load.

For integration tests against `FileSessionHandler`, use a temporary directory the same way `FileSessionHandlerTest` does: create it in `setUp()` with `Filesystem::makeDirectory()` and delete it in `tearDown()` with `Filesystem::deleteDirectory()`.

For integration tests against `PdoSessionHandler`, the test suite uses an in-process SQLite file database (`sqlite:<tempfile>`) to avoid an external service dependency. This works because `SqlitePdoSessionAdapter` creates a real `PDO` connection on the first call to `getConnection()`.

Mongo and Predis handler tests require live services. Skip them in environments where those services are not available by checking `extension_loaded('mongodb')` or catching a Predis connection exception in `setUp()`.

---

## Extending

To add a custom session backend, implement PHP's built-in `SessionHandlerInterface`:

```php
use SessionHandlerInterface;

final class ApcuSessionHandler implements SessionHandlerInterface
{
    public function __construct(private readonly int $ttl = 3600)
    {
    }

    public function open(string $savePath, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        return apcu_fetch('session_' . $id) ?: '';
    }

    public function write(string $id, string $data): bool
    {
        return apcu_store('session_' . $id, $data, $this->ttl);
    }

    public function destroy(string $id): bool
    {
        return apcu_delete('session_' . $id);
    }

    public function gc(int $maxLifetime): int|false
    {
        // APCu handles TTL-based eviction itself; no explicit gc needed.
        return 0;
    }
}
```

Pass the handler to `SessionManager` or register it as the `SessionHandlerInterface` alias in your container. No other changes are needed — `SessionManager::start()` calls `session_set_save_handler()` with whatever handler you provide.

---

## Recipes

### Configuring secure session cookies

Session cookie parameters are separate from the handler. Set them on the manager before calling `start()`:

```php
$manager->setCookieParams([
    'lifetime' => 0,         // 0 means the cookie expires when the browser closes
    'path'     => '/',
    'domain'   => '.example.com',
    'secure'   => true,      // only send over HTTPS
    'httponly' => true,       // not accessible via JavaScript
]);

$manager->start();
```

When you use `SessionHeadersMiddleware` in the Http pipeline, the middleware reads the active cookie params via `session_get_cookie_params()` and builds a `SetCookie` value object through the Cookie package. This means the `SameSite` attribute and any other cookie properties you have configured via `session.cookie_samesite` in `php.ini` are picked up automatically.

### Sharing sessions across nodes with Redis

Use `PredisSessionHandler` with a shared Redis instance so that any application node can serve any session:

```php
use Altair\Session\Handler\PredisSessionHandler;
use Predis\Client;

$client = new Client([
    'host'     => getenv('SESSION_REDIS_HOST'),
    'port'     => (int) getenv('SESSION_REDIS_PORT'),
    'password' => getenv('SESSION_REDIS_PASSWORD') ?: null,
    'scheme'   => 'tcp',
]);

$handler = new PredisSessionHandler($client, [
    'gc_maxlifetime' => (int) ini_get('session.gc_maxlifetime'),
    'prefix'         => 'sess:',
]);

$manager = new SessionManager($request, $handler);
$manager->start();
```

Each node reads and writes to the same Redis key space, so a load balancer can route subsequent requests from the same browser to any node. Because Redis stores session data as strings under a single key per session ID, there is no row-level locking. Concurrent requests that modify the same session can produce lost updates. Design your application so that within a single user's session, concurrent writes are either idempotent or guarded at the application level.

### Setting a session expiry

The session lifetime is controlled at two levels. The cookie lifetime determines how long the browser keeps the session cookie. The handler lifetime determines when the stored data is eligible for garbage collection.

```php
// Cookie-side: the browser will discard the cookie after 2 hours of inactivity
// (not 2 hours from now — setting lifetime 0 here means session cookie).
ini_set('session.gc_maxlifetime', 7200);   // 2 hours for all handlers
ini_set('session.cookie_lifetime', 7200);  // tell the browser too

$manager->setCookieParams(['lifetime' => 7200]);
$manager->start();
```

For the file handler, `$minutes` passed to the constructor is the read-side check: `FileSessionHandler::read()` returns an empty string if the file is older than `$minutes` minutes. Keep `$minutes * 60` in sync with `session.gc_maxlifetime` or the handler will expire sessions before GC removes the files.

For the Mongo handler, the handler's `write()` computes `time() + (int) ini_get('session.gc_maxlifetime')` as the `session_lifetime` field. The `read()` method filters on `session_lifetime >= now`, so the INI value is the single source of truth for MongoDB session expiry.

### Session fixation prevention on login

After a successful authentication check, regenerate the session ID before writing any privileged data:

```php
// 1. Accept the POST, validate credentials.
$credentials = validateLoginForm($request->getParsedBody());
$user = $userRepository->findByCredentials($credentials);

if ($user === null) {
    // Authentication failed — do not regenerate.
    return $response->withStatus(401);
}

// 2. Regenerate ID before elevating privileges.
//    $deletePrevious = true removes the old session from storage.
$manager->regenerateId(deletePrevious: true);

// 3. Now write privileged data into the new session.
$auth = $manager->getSessionBlock('auth');
$auth->set('user_id', $user->getId());
$auth->set('roles', $user->getRoles());
```

Never skip the regeneration step. An attacker who knows a valid pre-login session ID can use it after the user logs in and inherit the authenticated state — this is the session fixation vulnerability.

On logout, destroy the session entirely:

```php
$manager->destroy(); // clears data, destroys server-side record, and deletes the cookie
```

### Flash a success notice after a redirect

Use `SessionBlock::setFlash()` before issuing a redirect, then read and display it on the next request:

```php
// Request A — after saving a form:
$notices = $manager->getSessionBlock('ui.notices');
$notices->setFlash('success', 'Settings saved successfully.');
return $response->withStatus(302)->withHeader('Location', '/settings');

// Request B — the settings page handler:
$notices = $manager->getSessionBlock('ui.notices');
$message = $notices->getFlash('success');
// $message is 'Settings saved successfully.' on this request.
// On the next request it will be null — the counter expired it.
```

---

## Related packages

- [cookie.md](./cookie.md) — the wire format: `Set-Cookie` header construction, the `CookieManager`, and `SetCookie` value objects. `SessionHeadersMiddleware` uses this package to set the session cookie on the response.
- [http.md](./http.md) — `SessionHeadersMiddleware` and the `CacheLimiterInterface` hierarchy (`NoCacheLimiter`, `PublicCacheLimiter`, `PrivateCacheLimiter`, `PrivateNoExpireCacheLimiter`) that control the `Cache-Control` headers associated with session responses.
- [security.md](./security.md) — `Altair\Security\Support\Salt`, used by `CsrfToken::generateValue()` to produce the raw entropy for CSRF token values. CSRF token management is integrated directly into `SessionManager::getCsrfToken()`.
- [cache.md](./cache.md) — the PSR-6/16 caching layer. Session storage and the cache layer are independent; the Predis session handler and `PredisCacheItemStorage` can share a Redis instance but operate in separate key namespaces.

---

## Limitations

- **No distributed locking for the Predis handler.** `PredisSessionHandler` inherits the session management from `Predis\Session\Handler`, which stores the session as a single Redis string key. Concurrent requests for the same session ID issue separate `GET`/`SET` calls without any distributed lock, so the last write wins. If your application makes concurrent AJAX requests that modify session state, use an advisory or row lock at the application level or switch to a PDO handler.

- **File handler: no advisory locking.** `FileSessionHandler` uses PHP's internal file locking, which is handled by PHP's session extension itself (it opens the session file with `flock`). Writes within a single PHP process are serialised, but no cross-process advisory lock is visible to the handler code. The lock is held for the duration of the request's session lifetime.

- **SQLite advisory locks are not supported.** Calling `doAdvisoryLocking()` on `SqlitePdoSessionAdapter` throws `Error`. Use `LOCK_TRANSACTIONAL` (the default) for SQLite; the `BEGIN IMMEDIATE TRANSACTION` acquires a write lock on the database file before the read, which is the correct serialisation primitive for SQLite.

- **PostgreSQL before 9.5 does not support `INSERT ... ON CONFLICT`.** `PostgreSqlPdoSessionAdapter::getMergePdoStatement()` returns `null` for these versions. The adapter falls back to a separate UPDATE + INSERT sequence with a duplicate-key retry. This is functional but involves more round trips than the upsert path.

- **`gc()` in the PDO handler is deferred to `close()`.** This is intentional — garbage collection runs after the session transaction commits and locks are released. A side-effect is that if `close()` is never called (and the shutdown function does not fire), GC does not run. The shutdown function registered in `SessionManager`'s constructor is the safety net for this.

- **`SessionManager::getCookieParams()` recurses infinitely** (tracked in [#42](https://github.com/univeros/framework/issues/42)). The method as shipped calls `$this->getCookieParams()` rather than returning `$this->cookieParams`, so any invocation blows the stack. Until the source is fixed, read `$this->cookieParams` directly or call `session_get_cookie_params()`.

- **`MongoSessionHandlerConfiguration` default URI is a typo** (tracked in [#43](https://github.com/univeros/framework/issues/43)). When `SESSION_MONGO_URI` is unset, the configuration falls back to `mongodb://12.0.0.1/` — almost certainly intended to be `127.0.0.1`. Set `SESSION_MONGO_URI` explicitly in any environment that uses the Mongo handler.

- **No flash message support outside `SessionBlock`.** Flash counters are stored under `altair:session:flash` inside a block. If you write to `$_SESSION` directly (outside a block), flash lifecycle management does not apply.
