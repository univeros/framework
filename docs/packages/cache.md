# Cache

PSR-6 and PSR-16 caching with pluggable storage backends for filesystem, Redis, Predis, and Memcached.

**Package:** `univeros/cache`
**Namespace:** `Altair\Cache`
**PSR compliance:** PSR-6 (`psr/cache ^3`), PSR-16 (`psr/simple-cache ^3`)

---

## Introduction

The Cache package gives you a single, unified caching API that works across multiple storage backends. You write your application against either the PSR-6 `CacheItemPoolInterface` or the simpler PSR-16 `CacheInterface`, then choose a backend (filesystem, Redis via ext-redis, Redis via Predis, or Memcached) by swapping one storage class and its configuration. No application code changes when you change backends.

PSR-6 is the primary contract in this package. It gives you fine-grained control: you can read items, set values and expirations, and defer writes to storage until you call `commit()`. PSR-16 (`SimpleCache`) is a thin facade built on top of the same PSR-6 pool; it trades that control for a simpler `get`/`set`/`delete` interface. If you only need straightforward key-value reads and writes, use `SimpleCache`. If you need deferred saves, batch operations, or tag-aware invalidation, use `CacheItemPool` directly.

The package sits between your application logic and whatever persistence layer you choose for caching. It does not know about HTTP, sessions, or your domain model. It stores serializable PHP values indexed by string keys, optionally with a time-to-live. It does not provide cache warming, HTTP-level cache headers, or distributed locking; those concerns belong to the layers above it.

Tag-aware invalidation is supported through `TagAwareCacheItemInterface` and `TagAwareCacheItemPoolInterface`. Items can carry one or more tags, and you can invalidate all items sharing a tag in a single call. This is implemented as a layer above the storage backends; not every storage backend natively supports tags, so the tag index is managed by the pool.

---

## Installation

Install the package with Composer:

```bash
composer require univeros/cache
```

The package requires **PHP 8.3 or later**. Storage-specific extensions are optional at install time but required at runtime if you use the corresponding backend:

- **`ext-redis`**: needed for `RedisCacheItemStorage` (the ext-redis C extension communicates directly with Redis without a PHP client library)
- **`ext-memcached`**: needed for `MemcachedCacheItemStorage`; version 2.2.0 or later is required
- **`predis/predis`**: needed for `PredisCacheItemStorage` (a pure-PHP Redis client; use this when you cannot install ext-redis)

The `FilesystemCacheItemStorage` backend has no extension requirements beyond `univeros/filesystem`, which is pulled in automatically.

If you're using the full `univeros/framework` meta-package, this package is already included.

---

## Quick start

This example shows the minimum code to store and retrieve a value using the filesystem backend, which needs no external services:

```php
use Altair\Cache\CacheItemPool;
use Altair\Cache\Storage\FilesystemCacheItemStorage;
use Altair\Filesystem\Filesystem;

$pool = new CacheItemPool(
    store: new FilesystemCacheItemStorage(new Filesystem(), '/tmp/my-cache'),
    namespace: 'myapp',
    defaultLifespan: 3600,
);

$item = $pool->getItem('user.42.profile');

if (!$item->isHit()) {
    $profile = expensiveDatabaseQuery(42);
    $item->set($profile)->expiresAfter(3600);
    $pool->save($item);
}

$profile = $item->get();
```

`getItem()` always returns a `CacheItemInterface`, even on a miss. You check `isHit()` to tell a hit from a miss, then call `set()` and `save()` to populate the cache. The next call within the TTL will return the stored value directly from the cache.

---

## Concepts

### PSR-6 vs PSR-16

PSR-6 is the rich cache interface. You work with `CacheItemPoolInterface` (the pool) and `CacheItemInterface` (individual items). Items are first-class objects: you fetch them, inspect whether they were found, set their value and expiration, and save them back. Deferred saves let you batch multiple writes into a single storage round trip.

PSR-16 (`CacheInterface`) is the simplified interface. It reduces the API to `get/set/delete/has/clear` and their `*Multiple` variants. `SimpleCache` in this package is an adapter that wraps any `CacheItemPoolInterface` to expose that simpler surface.

Both interfaces are versioned at `^3` (2022 updates), which introduced nullable return types and tightened parameter types.

### CacheItem

`CacheItem` is the value object returned by the pool. Its internal state (`key`, `value`, `isHit`, `expirationTime`, and `defaultLifespan`) is `protected`. The pool populates these fields via `Closure::bind()` against the `CacheItem` class scope, which avoids exposing a public constructor while still keeping the class extensible. You set the value with `set()` and the expiration with `expiresAfter()` or `expiresAt()`.

### Storage backends

`CacheItemStorageInterface` is the internal contract between the pool and its backend. It defines `getItems`, `hasItem`, `clear`, `deleteItems`, and `save`. You never call this directly; the pool calls it on your behalf. Each concrete implementation handles one backend:

| Class | Backend | Extension |
|---|---|---|
| `FilesystemCacheItemStorage` | Local filesystem | none |
| `RedisCacheItemStorage` | Redis via ext-redis | `ext-redis` |
| `PredisCacheItemStorage` | Redis via Predis | `predis/predis` |
| `MemcachedCacheItemStorage` | Memcached | `ext-memcached` |
| `NullCacheItemStorage` | Discard everything | none |

### Namespaces

A namespace string prefixes every cache key in storage. When you set `namespace: 'myapp'`, a key `user.42` is stored as `myapp:<hashed>:user.42`. This lets multiple applications share the same Redis instance or cache directory without key collisions. Clearing the pool with `clear()` only removes items under that namespace prefix; the rest of the storage is untouched.

For Redis and Predis backends, the namespace is also passed directly to the storage class, which uses it as a Redis key prefix during bulk operations. Namespace strings for these backends are restricted to the character set `[-+_.A-Za-z0-9]`; any other character throws `InvalidArgumentException`.

### Lifetimes and expirations

The `defaultLifespan` constructor argument sets the fallback TTL in seconds for items whose expiration has not been explicitly set. Pass `0` to keep items indefinitely (until manually deleted or the storage is flushed).

Per-item expiration overrides the default. You have two ways to set it:

- `expiresAfter(int|DateInterval|null $time)`: relative to the current time
- `expiresAt(?DateTimeInterface $expiration)`: absolute timestamp; pass `null` to fall back to the default lifespan

When `commit()` runs, the deferred merger closure converts each item's expiration into a lifespan in seconds (the difference between the item's `expirationTime` and `time()`). Items already past their expiration are collected into an `$expired` list and deleted before the remaining items are saved.

### Key validation

All cache keys pass through `CacheItemKeyValidator` before being used in storage. A key must be a non-empty string and must not contain the reserved characters `{}()/\@:` (the PSR-6 reserved character set). Keys that fail validation throw `Altair\Cache\Exception\InvalidArgumentException`.

Key hashing happens automatically when the storage backend reports a `getMaxIdLength()`. When the namespaced key would exceed that limit (Memcached caps at 250 bytes minus the prefix key length), the pool replaces the key segment with a base64-encoded SHA-256 hash truncated to a safe length.

---

## Usage

### PSR-6 pool: basic read-write

The standard PSR-6 pattern is: get an item, check if it hit, set the value if not, save.

```php
$item = $pool->getItem('product.99');

if (!$item->isHit()) {
    $data = $productRepository->find(99);
    $item->set($data);
    $item->expiresAfter(600); // 10 minutes
    $pool->save($item);
}

$product = $item->get();
```

`getItem()` calls `commit()` on any pending deferred items first, ensuring a consistent view of the cache before the read.

### PSR-6 pool: bulk reads

Fetching multiple items in one call is more efficient than looping over `getItem()` because the storage layer can batch the underlying reads:

```php
$items = $pool->getItems(['product.1', 'product.2', 'product.3']);

foreach ($items as $key => $item) {
    if (!$item->isHit()) {
        // populate the miss ...
    }
}
```

`getItems()` returns a `Generator`. Items come back keyed by the original key you passed, not the internal namespaced ID. Any key not found in storage is still present in the generator; it simply returns an item where `isHit()` is `false`.

### PSR-6 pool: deferred saves

`saveDeferred()` queues an item without writing it to storage immediately. When you call `commit()`, all queued items are grouped by their lifespan and written in as few round trips as the backend allows. If the pool is destroyed before you call `commit()`, the destructor calls it automatically.

```php
foreach ($productList as $product) {
    $item = $pool->getItem('product.' . $product->id);
    $item->set($product);
    $item->expiresAfter(3600);
    $pool->saveDeferred($item);
}

// All items written in a single batch per lifespan group.
$pool->commit();
```

If a bulk write fails, the pool retries the failed items individually, logging each failure. This means a partial success is possible: some items may be written while others are not. Check the boolean return value of `commit()` if you need to know.

### PSR-6 pool: deletion

Delete a single item or a batch:

```php
$pool->deleteItem('product.99');
$pool->deleteItems(['product.1', 'product.2']);
```

Like saves, bulk deletes retry individually on failure and log each unsuccessful deletion.

### PSR-16 simple cache

Wrap any pool in `SimpleCache` when you only need the basic `get`/`set` API:

```php
use Altair\Cache\SimpleCache;

$cache = new SimpleCache($pool);

$value = $cache->get('report.monthly', default: []);
$cache->set('report.monthly', $value, ttl: 86400);
$cache->delete('report.monthly');
```

The `$default` parameter on `get()` and `getMultiple()` is returned when the key is not in the cache; it does not write anything to storage. Pass `null` for `$ttl` to use the pool's `defaultLifespan`.

`setMultiple()` uses `saveDeferred` + `commit()` internally, so it batches writes the same way a manual deferred loop would.

### Tag-aware invalidation

`TagAwareCacheItemInterface` extends `CacheItemInterface` with two immutable-style methods: `withTag(string $tag)` and `withTags(array $tags)`. Both return a new clone of the item with the additional tags merged in.

```php
use Altair\Cache\Contracts\TagAwareCacheItemInterface;
use Altair\Cache\Contracts\TagAwareCacheItemPoolInterface;

// $pool must implement TagAwareCacheItemPoolInterface
$item = $pool->getItem('article.5');
if (!$item->isHit()) {
    $item = $item
        ->withTag('articles')
        ->withTags(['author.3', 'category.news']);
    $item->set($articleData);
    $pool->save($item);
}

// Later, invalidate all items tagged 'author.3':
$pool->invalidateTag('author.3');
```

Tag strings follow the same reserved-character rules as keys: non-empty, no `{}()/\@:`. Invalid tags throw `InvalidArgumentException`.

### Available storage backends

#### Filesystem

The filesystem backend stores each item as a PHP file that returns a serialized object. Files are distributed into a two-level directory hierarchy derived from the SHA-256 hash of the cache directory path combined with the item ID. This prevents any one directory from growing too large.

```php
use Altair\Cache\Storage\FilesystemCacheItemStorage;
use Altair\Filesystem\Filesystem;

$storage = new FilesystemCacheItemStorage(
    filesystem: new Filesystem(),
    directory: '/var/cache/myapp',   // omit to use sys_get_temp_dir()/univeros-cache/
);
```

Items with `lifespan = 0` are stored with a one-year expiration (31,557,600 seconds). This is a deliberate design choice: the filesystem backend uses the file's mtime as the expiration sentinel in `hasItem()`, and a zero lifespan with no mtime would never expire through that path.

The directory must exist and be writable; the constructor throws `InvalidArgumentException` otherwise. On Windows, paths longer than 234 characters (leaving room for filename fragments within the 258-character limit) also throw.

#### Redis (ext-redis)

Use `RedisCacheItemStorage` when you have the `ext-redis` C extension installed:

```php
use Altair\Cache\Storage\RedisCacheItemStorage;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$storage = new RedisCacheItemStorage($redis, namespace: 'myapp');
```

Redis 2.8 or later is required; the constructor checks the server version and throws if the requirement is not met. Items are serialized with PHP's native `serialize()` and stored with `SETEX` when a TTL applies, or `MSET` for indefinite items.

When `clear()` is called with a namespace set, the backend uses `SCAN` with a cursor loop to find and delete all keys matching the namespace prefix. Without a namespace, it calls `FLUSHDB`. This distinction matters in shared Redis instances.

#### Redis (Predis)

Use `PredisCacheItemStorage` when you cannot install ext-redis or prefer a pure-PHP client:

```php
use Altair\Cache\Storage\PredisCacheItemStorage;
use Predis\Client;

$client = new Client(['host' => '127.0.0.1', 'port' => 6379]);
$storage = new PredisCacheItemStorage($client, namespace: 'myapp');
```

The API is identical to `RedisCacheItemStorage` from the pool's perspective. Internally, `PredisCacheItemStorage` uses Predis pipelines where `RedisCacheItemStorage` uses `Redis::PIPELINE`. One important difference: `clear()` against a native Redis cluster (`RedisCluster` connection type) always returns `false`; flushing a native cluster must be done by other means. This is documented in the source with a comment.

`CacheItemPool` detects when its storage is a `PredisCacheItemStorage` and calls `useNamespace()` directly on the storage object in addition to the normal prefix logic. Avoid passing a `PredisCacheItemStorage` instance to a pool with a namespace that already contains the Redis prefix, or you will end up with a double prefix.

#### Memcached

Pass a configured `Memcached` instance:

```php
use Altair\Cache\Storage\MemcachedCacheItemStorage;

$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);
$storage = new MemcachedCacheItemStorage($memcached);
```

The constructor enforces two requirements: the `memcached` extension must be version 2.2.0 or later, and the serializer option must be either `SERIALIZER_PHP` or `SERIALIZER_IGBINARY`. Any other serializer throws `CacheException`.

Memcached imposes a 250-byte key length limit. The constructor reads the `OPT_PREFIX_KEY` option and adjusts `$maxIdLength` accordingly, so the pool's key hashing logic will hash long keys before they reach Memcached.

#### Null backend

`NullCacheItemStorage` accepts all writes and returns nothing on reads. It is the right choice for testing code that depends on a cache pool without wanting to interact with any real storage:

```php
use Altair\Cache\Storage\NullCacheItemStorage;

$pool = new CacheItemPool(new NullCacheItemStorage());
```

Every `save()` call returns `false`, `hasItem()` always returns `false`, and `getItems()` always returns an empty array. The pool layer still operates normally (deferred queuing, commit, and logging all work); the items just never persist.

---

## Configuration

The `Configuration/` directory contains four `ConfigurationInterface` implementations that wire storage backends into an `Altair\Container\Container`. Each reads connection settings from environment variables via `EnvAwareTrait`.

### FilesystemCacheItemStorageConfiguration

```php
use Altair\Cache\Configuration\FilesystemCacheItemStorageConfiguration;

(new FilesystemCacheItemStorageConfiguration())->apply($container);
```

Environment variable consumed:

| Variable | Default |
|---|---|
| `CACHE_FS_DIRECTORY` | `sys_get_temp_dir() . '/altair-cache'` |

After `apply()`, `CacheItemStorageInterface` resolves to `FilesystemCacheItemStorage` in the container.

### RedisCacheItemStorageConfiguration

Despite its name, this configuration wires `PredisCacheItemStorage` (not `RedisCacheItemStorage`). It is the container-friendly Redis configuration that uses the Predis library rather than ext-redis.

```php
use Altair\Cache\Configuration\RedisCacheItemStorageConfiguration;

(new RedisCacheItemStorageConfiguration())->apply($container);
```

Environment variables consumed:

| Variable | Default |
|---|---|
| `CACHE_REDIS_HOST` | `localhost` |
| `CACHE_REDIS_PORT` | `6379` |

### PredisCacheItemStorageConfiguration

Identical to `RedisCacheItemStorageConfiguration` in its implementation; both wire `PredisCacheItemStorage` against `CACHE_REDIS_HOST` and `CACHE_REDIS_PORT`. You'll typically use one or the other, not both.

### MemcachedCacheItemStorageConfiguration

```php
use Altair\Cache\Configuration\MemcachedCacheItemStorageConfiguration;

(new MemcachedCacheItemStorageConfiguration())->apply($container);
```

Environment variables consumed:

| Variable | Default |
|---|---|
| `CACHE_MEMCACHED_HOST` | `localhost` |
| `CACHE_MEMCACHED_PORT` | `11211` |

Note: the weight parameter (`CACHE_MEMCACHED_WEIGHT`) is present in the source as a commented-out line. It is not currently wired; if you need weighted server pools, instantiate `MemcachedCacheItemStorage` manually.

### Wiring the pool itself

The configuration classes only bind the storage implementation. You still need to register the pool. You'll typically do this with a delegate factory:

```php
use Altair\Cache\CacheItemPool;
use Altair\Cache\Contracts\CacheItemStorageInterface;

$container->delegate(
    CacheItemPool::class,
    static function () use ($container): CacheItemPool {
        return new CacheItemPool(
            store: $container->make(CacheItemStorageInterface::class),
            namespace: $_ENV['CACHE_NAMESPACE'] ?? 'app',
            defaultLifespan: (int) ($_ENV['CACHE_LIFETIME'] ?? 3600),
        );
    }
);
```

---

## Testing

For unit and integration tests, reach for `NullCacheItemStorage` or `FilesystemCacheItemStorage` with a temporary directory. Both avoid external service dependencies.

The test suite uses `FilesystemCacheItemStorage` with a per-test `tmp/` subdirectory, created in `setUp()` and deleted in `tearDown()`:

```php
protected function setUp(): void
{
    $this->fs = new Filesystem();
    $this->fs->makeDirectory(__DIR__ . '/tmp');
    $this->pool = new CacheItemPool(
        new FilesystemCacheItemStorage($this->fs, __DIR__ . '/tmp')
    );
}

protected function tearDown(): void
{
    $this->pool->clear();
    $this->fs->deleteDirectory(__DIR__ . '/tmp');
}
```

`AbstractStorageTestCase` in `tests/Cache/` provides a shared base for storage backend tests. Extend it and assign `$this->store` in `setUp()` to run the standard read/write/delete/clear contract against any backend.

Redis and Memcached integration tests (`tests/Cache/RedisCacheItemStorageTest.php`, `MemcachedCacheItemStorageTest.php`, `PredisCacheItemStorageTest.php`) require live services. In CI, Docker containers provide these services. Run them locally with:

```bash
docker run -d -p 6379:6379 redis:7
docker run -d -p 11211:11211 memcached:1
```

---

## Extending

To add a custom backend, implement `CacheItemStorageInterface`:

```php
use Altair\Cache\Contracts\CacheItemStorageInterface;

final class ApcuCacheItemStorage implements CacheItemStorageInterface
{
    public function getMaxIdLength(): ?int
    {
        return null; // APCu has no practical key length limit
    }

    public function getItems(array $keys = []): array
    {
        $items = [];
        foreach ($keys as $key) {
            $value = apcu_fetch($key, $success);
            if ($success) {
                $items[$key] = $value;
            }
        }
        return $items;
    }

    public function hasItem(string $key): bool
    {
        return apcu_exists($key);
    }

    public function clear(): bool
    {
        return apcu_clear_cache();
    }

    public function deleteItems(array $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = apcu_delete($key) && $success;
        }
        return $success;
    }

    public function save(array $values, int $lifespan): bool|array
    {
        $failed = [];
        foreach ($values as $id => $value) {
            if (!apcu_store($id, $value, $lifespan)) {
                $failed[] = $id;
            }
        }
        return $failed === [] ? true : $failed;
    }
}
```

A few things to note when writing a custom storage:

- `save()` must return `true` on complete success or an array of failed IDs on partial failure. The pool uses that return value to decide whether to retry and what to log.
- `getMaxIdLength()` returning `null` means no limit. Return an integer to trigger the pool's automatic key hashing for long keys.
- Values arriving in `save()` are already namespaced by the pool. Do not apply a second prefix unless you manage the namespace yourself (as Redis and Predis backends do for their `clear()` implementation).
- Values arriving in `getItems()` are raw storage bytes for Redis/Predis (deserialized by `CacheItemUnserializer`) or raw PHP values for filesystem and Memcached. Your custom backend owns serialization and deserialization.

---

## Recipes

### Cache an expensive computation

Wrap a slow function so it runs at most once per TTL period:

```php
function cachedReport(CacheItemPoolInterface $pool, int $month, int $year): array
{
    $key = 'report.' . $year . '.' . $month;
    $item = $pool->getItem($key);

    if ($item->isHit()) {
        return $item->get();
    }

    $report = generateMonthlyReport($month, $year); // slow

    $item->set($report);
    $item->expiresAfter(86400); // re-compute daily
    $pool->save($item);

    return $report;
}
```

If two requests arrive simultaneously before the first has populated the cache, both will call `generateMonthlyReport()`. The Cache package does not include locking. Implement a distributed lock (for example, via a Redis SETNX pattern) if stampede protection is important for your use case.

### Share a cache between web nodes with Redis

Use Predis (no extension required) with a shared Redis instance:

```php
use Altair\Cache\CacheItemPool;
use Altair\Cache\Storage\PredisCacheItemStorage;
use Predis\Client;

$client = new Client([
    'host' => getenv('CACHE_REDIS_HOST'),
    'port' => (int) getenv('CACHE_REDIS_PORT'),
]);

$pool = new CacheItemPool(
    store: new PredisCacheItemStorage($client, namespace: 'web'),
    namespace: 'web',
    defaultLifespan: 3600,
);
```

All nodes sharing the same Redis instance and namespace will see the same cached values. When a node calls `clear()`, it only removes keys prefixed with the namespace, leaving other applications' keys intact.

### Batch-warm a cache after deployment

Deferred saves batch multiple writes efficiently. This is useful for warming a cache after a deployment:

```php
$products = $productRepository->findAll();

foreach ($products as $product) {
    $item = $pool->getItem('product.' . $product->id);
    $item->set($product->toArray());
    $item->expiresAfter(7200);
    $pool->saveDeferred($item);
}

$success = $pool->commit();

if (!$success) {
    $logger->warning('Cache warm-up partially failed.');
}
```

The pool groups deferred items by their computed lifespan and writes each group in a single `save()` call to the storage backend. For Redis and Predis, this means a single pipeline execution per lifespan group.

### Invalidate a category of items with tags

Use `TagAwareCacheItemInterface` when you need to invalidate a set of related items without knowing their individual keys:

```php
// Store article with tags at write time
$item = $pool->getItem('article.' . $id);
$item = $item->withTags(['articles', 'author.' . $authorId]);
$item->set($articleData)->expiresAfter(3600);
$pool->save($item);

// Later, when the author's name changes, invalidate all their articles:
$pool->invalidateTag('author.' . $authorId);
```

Tags are stored alongside the item. `invalidateTags()` accepts an array for bulk invalidation across multiple tags in one call.

### Use SimpleCache as a PSR-16 drop-in

If you're integrating with a library that accepts `Psr\SimpleCache\CacheInterface`, wrap any pool:

```php
use Altair\Cache\SimpleCache;

$cache = new SimpleCache($pool);

// PSR-16 compliant — pass to any library expecting CacheInterface
$htmlPurifier = new HTMLPurifier(HTMLPurifier_Config::create([
    'Cache.DefinitionImpl' => null, // use external PSR-16 cache
]));
```

`setMultiple()` uses `saveDeferred` + `commit()` under the hood, so bulk writes are batched even through the PSR-16 interface.

---

## Related packages

- [Container](./container.md): PSR-11 DI container used by the `Configuration/` classes to wire storage backends
- [Configuration](./configuration.md): `EnvAwareTrait` and `ConfigurationInterface` that the cache configurations implement
- [Filesystem](./filesystem.md): Flysystem v3 adapter used by `FilesystemCacheItemStorage`
- [Session](./session.md): Session handlers that can use Redis as a backend (separate from the cache namespace)

---

## Migration notes

### PSR-6 v3 and PSR-16 v3 (2026-05 modernization sweep)

The 2026-05 modernization bumped `psr/cache` and `psr/simple-cache` from `^1` to `^3`. Both standards introduced breaking changes in their major versions:

**PSR-6 v3 signature changes relevant to this package:**

- `CacheItemInterface::get()` now returns `mixed` (was untyped).
- `CacheItemInterface::set()` now returns `static` (was `self`).
- `CacheItemInterface::expiresAt()` and `expiresAfter()` now accept and return `static` (was `self`).
- The `$key` parameter of `CacheItemPoolInterface::getItem()` is now typed `string` (was untyped).

`CacheItem` and `CacheItemPool` in this package fully reflect these signatures. If you were calling any cache method and relying on the old untyped return, update your type hints.

**PSR-16 v3 signature changes relevant to this package:**

- `CacheInterface::set()` now has `null|int|DateInterval $ttl` (typed union, was untyped).
- `CacheInterface::getMultiple()` and `setMultiple()` accept `iterable` (tightened from mixed).
- `CacheInterface::deleteMultiple()` accepts `iterable`.

`SimpleCache` in this package uses `#[\Override]` attributes on all interface methods, so a mismatch between the implementation and the interface would be caught at class-loading time.

### Namespace double-prefix with Predis

In the pre-modernization codebase, `CacheItemPool` did not call `useNamespace()` on `PredisCacheItemStorage`. If you were relying on the pool's namespace prefix being applied on top of a Predis-level namespace, the behavior has changed. The pool now detects `PredisCacheItemStorage` and delegates namespace responsibility to the storage object, then skips the internal pool prefix for Predis. Pass the namespace once (to the pool constructor), not twice.

### Filesystem zero-lifespan behavior

Items saved with `lifespan = 0` through the filesystem backend are stored with a one-year expiry (31,557,600 seconds from the time of `save()`). This was always the case; it is not a new behavior. The implication is that "indefinite" items on the filesystem backend do expire after one year. If you need truly indefinite filesystem caching, you will need to refresh items before they reach the one-year mark.

---

## Limitations

- **No distributed locking.** Concurrent writes to the same key are not serialized. Use a Redis SETNX or similar pattern if you need cache stampede protection.
- **No atomic compare-and-swap.** There is no `checkAndSet()` equivalent. The pool's `saveDeferred`/`commit` cycle is not atomic across multiple keys.
- **Tag storage is not handled by the storage backends.** `TagAwareCacheItemInterface` and `TagAwareCacheItemPoolInterface` define the tag API contracts, but there is no concrete `TagAwareCacheItemPool` implementation shipped in this package. You would need to provide one that manages the tag index. The traits (`TagsAwareTrait`) and validators are available to build it with.
- **Memcached `clear()` flushes the entire server.** `MemcachedCacheItemStorage::clear()` calls `flush()` on the Memcached client, which clears all keys on that server, regardless of any logical namespace. There is no namespace-scoped clear for Memcached; the protocol does not support it.
- **Predis with native Redis Cluster cannot `clear()`.** As documented in the source, `PredisCacheItemStorage::clear()` returns `false` when the connection is a `RedisCluster`. You must flush the cluster nodes by other means.
- **No PSR-14 event hooks.** Cache hits, misses, and evictions do not emit events. If you need observability hooks, wrap the pool in a decorator that emits events from your chosen PSR-14 dispatcher.
- **`CacheItem` constructor is not public.** Items are produced exclusively by the pool via a bound closure. You cannot instantiate a `CacheItem` directly in application code, which means you cannot pre-build items outside a pool.
