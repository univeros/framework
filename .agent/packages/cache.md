# univeros/cache  ·  Altair\Cache

**Purpose:** The Altair Cache package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `CacheItemKeyValidatorInterface` | `validate(mixed)` | `bool` | extends `FailureReasonAwareInterface` |
| `CacheItemStorageInterface` | `clear()` | `bool` |  |
|  | `deleteItems(array)` | `bool` |  |
|  | `getItems(array)` | `array` |  |
|  | `getMaxIdLength()` | `int\|null` |  |
|  | `hasItem(string)` | `bool` |  |
|  | `save(array, int)` | `mixed` |  |
| `CacheItemTagValidatorInterface` | `validate(string)` | `bool` | extends `FailureReasonAwareInterface` |
| `FailureReasonAwareInterface` | `getFailureReason()` | `string\|null` |  |
| `TagAwareCacheItemInterface` | `withTag(string)` | `TagAwareCacheItemInterface` | extends `CacheItemInterface` |
|  | `withTags(array)` | `TagAwareCacheItemInterface` |  |
| `TagAwareCacheItemPoolInterface` | `getItem(mixed)` | `TagAwareCacheItemInterface` | extends `CacheItemPoolInterface` |
|  | `getItems(array)` | `iterable` |  |
|  | `invalidateTag(string)` | `bool` |  |
|  | `invalidateTags(array)` | `bool` |  |

## Concrete classes

- `CacheItem` _(final)_ — implements `CacheItemInterface`
- `CacheItemKeyValidator` — implements `CacheItemKeyValidatorInterface`, `FailureReasonAwareInterface`
- `CacheItemPool` — implements `CacheItemPoolInterface`, `LoggerAwareInterface`
- `CacheItemTagValidator` — implements `CacheItemTagValidatorInterface`, `FailureReasonAwareInterface`
- `CacheItemUnserializer`
- `FilesystemCacheItemStorage` — implements `CacheItemStorageInterface`
- `FilesystemCacheItemStorageConfiguration` — implements `ConfigurationInterface`
- `MemcachedCacheItemStorage` — implements `CacheItemStorageInterface`
- `MemcachedCacheItemStorageConfiguration` — implements `ConfigurationInterface`
- `NullCacheItemStorage` — implements `CacheItemStorageInterface`
- `PredisCacheItemStorage` — implements `CacheItemStorageInterface`
- `PredisCacheItemStorageConfiguration` — implements `ConfigurationInterface`
- `RedisCacheItemStorage` — implements `CacheItemStorageInterface`
- `RedisCacheItemStorageConfiguration` — implements `ConfigurationInterface`
- `SimpleCache` — implements `CacheInterface`

## Tests as documentation

- `tests/Cache/CacheItemKeyValidatorTest.php`
- `tests/Cache/CacheItemPoolTest.php`
- `tests/Cache/CacheItemTest.php`
- `tests/Cache/FilesystemCacheItemStorageTest.php`
- `tests/Cache/MemcachedCacheItemStorageTest.php`
- `tests/Cache/PredisCacheItemStorageTest.php`
- `tests/Cache/RedisCacheItemStorageTest.php`
- `tests/Cache/SimpleCacheTest.php`

## Related packages

- `psr/cache`
- `psr/log`
- `psr/simple-cache`
- `univeros/configuration`
- `univeros/filesystem`
