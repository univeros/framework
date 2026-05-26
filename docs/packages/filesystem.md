# Filesystem

A Flysystem v3 wrapper that gives you a single, swap-friendly API for local disks, S3, SFTP, FTP, and Dropbox — wired to the Altair container with zero boilerplate.

---

## Composer package and namespace

| | |
|---|---|
| **Composer package** | `univeros/filesystem` |
| **PHP namespace** | `Altair\Filesystem` |
| **PHP requirement** | `>= 8.3` |
| **Core dependency** | `league/flysystem ^3.29` |

---

## Introduction

File storage is one of the first concerns that looks trivial and turns into a liability the moment you need to swap a local disk for cloud storage, or run tests without touching a real filesystem. Hardcoding `file_put_contents` and `fopen` calls spreads storage logic across your codebase, making it nearly impossible to change the underlying medium without rewriting dozens of call sites.

[League Flysystem](https://flysystem.thephpleague.com/) solves this by providing a unified `FilesystemOperator` interface that works identically regardless of the storage backend. Your application code writes to `$fs->write('path/to/file.txt', $content)` whether the underlying medium is your local disk, an S3 bucket, or an SFTP server.

This package builds on Flysystem v3 in three concrete ways. First, it provides `Altair\Filesystem\Adapter\FlysystemAdapter` — a decorator that wraps any `FilesystemOperator` and adds four methods that Flysystem's core interface deliberately omits: `exists()` (unified file-or-directory check), `prepend()`, `append()`, and `listDirectories()`. Second, it provides a set of `Configuration\*` classes that bind each supported adapter into the Altair DI container by reading environment variables, so you can swap adapters by changing `.env` entries rather than touching code. Third, it ships a standalone `Filesystem` class with PHP-native helpers (locking reads, chmod, glob, symlinks, recursive directory copy) that complement the Flysystem path for operations that are local-only by nature.

Four adapter backends ship out of the box: **Local** (`league/flysystem` core), **AWS S3** (`league/flysystem-aws-s3-v3`), **SFTP** (`league/flysystem-sftp-v3` via phpseclib v3), **FTP** (`league/flysystem-ftp`), and **Dropbox** (`spatie/flysystem-dropbox`). The cloud adapters are `suggest`-ed, not required — install only what you need.

---

## Installation

The core package and the local adapter are included in the framework meta-package:

```bash
composer require univeros/filesystem
```

If you are using only the local filesystem, no further packages are needed. For cloud and protocol adapters, install the specific bridge:

```bash
# AWS S3
composer require league/flysystem-aws-s3-v3

# SFTP (phpseclib v3)
composer require league/flysystem-sftp-v3

# FTP
composer require league/flysystem-ftp

# Dropbox (Spatie bridge)
composer require spatie/flysystem-dropbox
```

When you install the full framework via `composer require univeros/framework`, these are already available as suggestions in the root manifest — run `composer require` on the individual bridge package to activate it.

---

## Quick start

This example shows how to get a working local-disk `FlysystemAdapter` without the DI container. It is the fastest path to verifying the package works and the pattern you will use in tests.

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

// Point at an absolute path on disk.
$local = new LocalFilesystemAdapter('/var/app/storage');

// Wrap it in a Flysystem operator, then in the Altair decorator.
$fs = new FlysystemAdapter(new Filesystem($local));

// Write a file. The path is relative to /var/app/storage.
$fs->write('uploads/hello.txt', 'Hello, Altair!');

// Read it back.
$contents = $fs->read('uploads/hello.txt');

// Check existence (file or directory — exists() handles both).
$exists = $fs->exists('uploads/hello.txt'); // true

// Append a line.
$fs->append('uploads/hello.txt', 'Second line');

// Delete it.
$fs->delete('uploads/hello.txt');
```

---

## Concepts

### The two-layer design

The package exposes two separate classes with different scopes:

- **`Altair\Filesystem\Filesystem`** — a local-only utility class built on PHP's native filesystem functions. Use it when you know the target is always a local disk and you need features like locking reads, symlinks, `require_once`, `chmod`, or pattern-based glob listing. It throws `Altair\Filesystem\Exception\FileNotFoundException` and `InvalidArgumentException` for invalid inputs.

- **`Altair\Filesystem\Adapter\FlysystemAdapter`** — a decorator around any Flysystem v3 `FilesystemOperator`. Use this when the storage backend might change, when you write tests with the in-memory adapter, or when you need the unified API across S3 / SFTP / FTP / Dropbox.

In most application code you will use `FlysystemAdapter` exclusively. The `Filesystem` utility class is useful for bootstrap tasks, configuration loading, and other operations that are inherently tied to the local disk.

### `FilesystemAdapterInterface`

`Altair\Filesystem\Contracts\FilesystemAdapterInterface` extends `League\Flysystem\FilesystemOperator` directly. This means any object that satisfies the interface also satisfies the full Flysystem operator contract — all core read/write/move/copy/list operations are available. The interface adds four methods on top:

| Method | Signature | What it adds |
|---|---|---|
| `exists` | `exists(string $path): bool` | Returns `true` for either a file or a directory at the given path. |
| `prepend` | `prepend(string $path, string $data, string $separator = PHP_EOL): void` | Writes `$data` before existing content; creates the file if absent. |
| `append` | `append(string $path, string $data, string $separator = PHP_EOL): void` | Writes `$data` after existing content; creates the file if absent. |
| `listDirectories` | `listDirectories(string $directory = '', bool $recursive = false): array` | Returns `list<string>` of directory paths inside `$directory`. |

The `$separator` parameter on `prepend`/`append` defaults to `PHP_EOL`, so each call adds a newline boundary between the existing content and the new data. Pass `''` to concatenate without a separator.

### Available adapters

| Adapter | Configuration class | Required package |
|---|---|---|
| Local | `LocalAdapterConfiguration` | `league/flysystem` (core) |
| AWS S3 v3 | `AwsS3AdapterConfiguration` | `league/flysystem-aws-s3-v3` |
| SFTP | `SftpAdapterConfiguration` | `league/flysystem-sftp-v3` |
| FTP | `FtpAdapterConfiguration` | `league/flysystem-ftp` |
| Dropbox | `DropboxAdapterConfiguration` | `spatie/flysystem-dropbox` |

### Container wiring pattern

Each adapter configuration class implements `Altair\Configuration\Contracts\ConfigurationInterface` and follows the same two-step pattern:

1. It delegates the concrete adapter class (e.g. `LocalFilesystemAdapter::class`) to a factory closure that reads environment variables via `EnvAwareTrait`.
2. It aliases `League\Flysystem\FilesystemAdapter::class` to the concrete class, so `FilesystemAdapterConfiguration` can resolve the correct adapter.

`FilesystemAdapterConfiguration` then delegates `FilesystemOperator::class` to a factory that instantiates `League\Flysystem\Filesystem` with whatever `FilesystemAdapter` the container resolves. Apply `FilesystemAdapterConfiguration` after the adapter-specific configuration, and the container binds the full pipeline.

### Path semantics

All paths passed to `FlysystemAdapter` are relative to the root configured for the adapter. A leading slash is not required and its presence or absence is normalized by Flysystem. Do not pass absolute paths — pass `'uploads/image.png'`, not `'/var/app/storage/uploads/image.png'`.

---

## Usage

### Local filesystem

The local adapter stores files in a directory on the server. It respects POSIX visibility (read/write/execute bits via `PortableVisibilityConverter`) and refuses to follow symlinks by default.

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

$adapter = new LocalFilesystemAdapter(
    '/var/app/storage',
    PortableVisibilityConverter::fromArray([
        'file' => ['public' => 0644, 'private' => 0600],
        'dir'  => ['public' => 0755, 'private' => 0700],
    ]),
    LOCK_EX,
    LocalFilesystemAdapter::DISALLOW_LINKS,
);

$fs = new FlysystemAdapter(new Filesystem($adapter));

$fs->write('reports/monthly.csv', $csvContent);
$fs->setVisibility('reports/monthly.csv', 'private');
```

### AWS S3

The S3 adapter requires `league/flysystem-aws-s3-v3` and a valid set of AWS credentials. Set these environment variables before booting the application:

```
FS_AWS_S3_KEY=AKIAIOSFODNN7EXAMPLE
FS_AWS_S3_SECRET=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
FS_AWS_S3_REGION=eu-west-1
FS_AWS_S3_VERSION=latest
FS_AWS_S3_BUCKET=my-app-uploads
FS_AWS_S3_PREFIX=          # optional path prefix inside the bucket
```

When you apply `AwsS3AdapterConfiguration` to the container, it resolves these at boot time. If you prefer to construct the adapter manually:

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

$s3 = new S3Client([
    'credentials' => ['key' => $key, 'secret' => $secret],
    'region'      => 'eu-west-1',
    'version'     => 'latest',
]);

$fs = new FlysystemAdapter(
    new Filesystem(new AwsS3V3Adapter($s3, 'my-app-uploads')),
);

$fs->write('avatars/user-42.png', $pngData);
$url = $fs->publicUrl('avatars/user-42.png');
```

`publicUrl()` and `temporaryUrl()` are forwarded to the underlying Flysystem operator. They are available on `FlysystemAdapter` as direct methods (not part of the `FilesystemAdapterInterface`) because they are adapter-specific capabilities not guaranteed by all backends.

### SFTP

The SFTP adapter requires `league/flysystem-sftp-v3` (which pulls in `phpseclib/phpseclib ^3`).

```
FS_SFTP_HOST=sftp.example.com
FS_SFTP_USERNAME=deploy
FS_SFTP_PASSWORD=              # leave blank if using a private key
FS_SFTP_PRIVATE_KEY=/home/app/.ssh/id_rsa
FS_SFTP_PASSPHRASE=            # passphrase for the private key, if any
FS_SFTP_PORT=22
FS_SFTP_USE_AGENT=false
FS_SFTP_TIMEOUT=10
FS_SFTP_MAX_TRIES=4
FS_SFTP_HOST_FINGERPRINT=      # optional; validates the remote host key
FS_SFTP_ROOT=/var/www/uploads
```

Constructing manually:

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

$provider = new SftpConnectionProvider(
    host:            'sftp.example.com',
    username:        'deploy',
    password:        null,
    privateKey:      '/home/app/.ssh/id_rsa',
    passphrase:      null,
    port:            22,
    useAgent:        false,
    timeout:         10,
    maxTries:        4,
    hostFingerprint: null,
);

$fs = new FlysystemAdapter(
    new Filesystem(
        new SftpAdapter($provider, '/var/www/uploads', PortableVisibilityConverter::fromArray([])),
    ),
);
```

### FTP

The FTP adapter requires `league/flysystem-ftp`.

```
FS_FTP_HOST=ftp.example.com
FS_FTP_ROOT=/uploads
FS_FTP_USERNAME=ftpuser
FS_FTP_PASSWORD=secret
FS_FTP_PORT=21
FS_FTP_SSL=false
FS_FTP_TIMEOUT=90
FS_FTP_PASSIVE=true
FS_FTP_TRANSFER_MODE=         # defaults to FTP_BINARY
FS_FTP_SYSTEM_TYPE=           # optional
FS_FTP_IGNORE_PASSIVE_ADDRESS=# optional
FS_FTP_RECURSE_MANUALLY=false
```

`FtpAdapterConfiguration` filters out `null` values before calling `FtpConnectionOptions::fromArray`, so you only need to set the options you actually need.

### Dropbox

The Dropbox adapter requires `spatie/flysystem-dropbox` (which pulls in `spatie/dropbox-api`).

```
FS_DROPBOX_ACCESS_TOKEN=sl.AAAA…
FS_DROPBOX_PREFIX=             # optional path prefix inside your Dropbox
```

Manual construction:

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;

$fs = new FlysystemAdapter(
    new Filesystem(
        new DropboxAdapter(new Client($accessToken)),
    ),
);
```

### The decorator — what it adds

The `FlysystemAdapter` decorator forwards every method from `FilesystemOperator` to the wrapped driver unchanged. On top of that it adds:

**`exists(string $path): bool`** — Flysystem v3 splits existence checks into `fileExists()` and `directoryExists()`. This unified method returns `true` if either check passes, so you do not need to know the path type upfront.

**`prepend(string $path, string $data, string $separator = PHP_EOL): void`** — reads the current content (empty string if the file does not exist), then writes `$data . $separator . $existing` back. The file-does-not-exist case creates the file with `$data` as its only content.

**`append(string $path, string $data, string $separator = PHP_EOL): void`** — same pattern, but appends: writes `$existing . $separator . $data`. Creates the file if it does not exist.

**`listDirectories(string $directory = '', bool $recursive = false): array`** — calls `listContents` and filters to `DirectoryAttributes` entries, returning a `list<string>` of paths. Shallow by default; pass `true` for a recursive listing.

**`publicUrl(string $path, array $config = []): string`** and **`temporaryUrl(string $path, \DateTimeInterface $expiresAt, array $config = []): string`** — forwarded directly to the underlying operator. These are not part of the interface because not all adapters support them (local disk does not produce meaningful public URLs).

**`checksum(string $path, array $config = []): string`** — forwarded to the underlying operator. On S3 this returns the ETag; on local filesystem it returns an MD5 hash.

### Common operations

```php
// Read
$contents = $fs->read('data/config.json');
$stream   = $fs->readStream('media/video.mp4');

// Write
$fs->write('data/config.json', json_encode($config, JSON_THROW_ON_ERROR));
$fs->writeStream('media/video.mp4', $resource);

// Exists (file or directory)
if ($fs->exists('cache/results.json')) { /* ... */ }

// Exists — typed checks
$fs->fileExists('cache/results.json');
$fs->directoryExists('cache');
$fs->has('cache/results.json');   // alias from FilesystemOperator

// Copy and move
$fs->copy('original.pdf', 'archive/original.pdf');
$fs->move('uploads/tmp_42.png', 'avatars/user-42.png');

// Delete
$fs->delete('tmp/scratch.txt');
$fs->deleteDirectory('tmp');

// List
$listing = $fs->listContents('uploads', false);
foreach ($listing as $item) {
    echo $item->path() . PHP_EOL;
}

$dirs = $fs->listDirectories('uploads', true); // recursive

// Metadata
$mime     = $fs->mimeType('documents/report.pdf');
$size     = $fs->fileSize('documents/report.pdf');
$modified = $fs->lastModified('documents/report.pdf');

// Visibility
$fs->setVisibility('documents/report.pdf', 'public');
$visibility = $fs->visibility('documents/report.pdf'); // 'public' | 'private'

// Append / prepend
$fs->append('logs/app.log', date('c') . ' Request processed');
$fs->prepend('queue/tasks.txt', 'high-priority-task');

// Create directory
$fs->createDirectory('cache/thumbnails');
```

---

## Configuration

### Container-wired setup

Apply configurations to the container in this order:

```php
<?php

declare(strict_types=1);

use Altair\Container\Container;
use Altair\Filesystem\Configuration\LocalAdapterConfiguration;
use Altair\Filesystem\Configuration\FilesystemAdapterConfiguration;

$container = new Container();

// Step 1: register the concrete adapter.
(new LocalAdapterConfiguration())->apply($container);

// Step 2: wire the adapter into a FilesystemOperator.
(new FilesystemAdapterConfiguration())->apply($container);

// Now the container can produce a FilesystemOperator.
$operator = $container->make(\League\Flysystem\FilesystemOperator::class);
```

Swap `LocalAdapterConfiguration` for any of the other configuration classes to change the backend.

### Environment variables by adapter

**Local**

| Variable | Default | Description |
|---|---|---|
| `FS_LOCAL_PATH` | — | **Required.** Absolute path to the storage root. |
| `FS_LOCAL_LOCK` | `LOCK_EX` | File locking mode for writes. |
| `FS_LOCAL_DISALLOW_LINKS` | `LocalFilesystemAdapter::DISALLOW_LINKS` | Symlink policy. |

**AWS S3**

| Variable | Default | Description |
|---|---|---|
| `FS_AWS_S3_KEY` | — | **Required.** AWS access key ID. |
| `FS_AWS_S3_SECRET` | — | **Required.** AWS secret access key. |
| `FS_AWS_S3_REGION` | — | **Required.** AWS region (e.g. `eu-west-1`). |
| `FS_AWS_S3_BUCKET` | — | **Required.** Bucket name. |
| `FS_AWS_S3_VERSION` | `latest` | S3 API version. |
| `FS_AWS_S3_PREFIX` | `''` | Optional path prefix inside the bucket. |

**SFTP**

| Variable | Default | Description |
|---|---|---|
| `FS_SFTP_HOST` | — | **Required.** Hostname. |
| `FS_SFTP_USERNAME` | — | **Required.** Username. |
| `FS_SFTP_PASSWORD` | — | Password (or use a private key). |
| `FS_SFTP_PRIVATE_KEY` | — | Path to private key file. |
| `FS_SFTP_PASSPHRASE` | — | Passphrase for the private key. |
| `FS_SFTP_PORT` | `22` | Port. |
| `FS_SFTP_USE_AGENT` | `false` | Use SSH agent. |
| `FS_SFTP_TIMEOUT` | `10` | Connection timeout in seconds. |
| `FS_SFTP_MAX_TRIES` | `4` | Retry attempts. |
| `FS_SFTP_HOST_FINGERPRINT` | — | Optional host fingerprint for verification. |
| `FS_SFTP_ROOT` | `/` | Root path on the remote server. |

**FTP**

| Variable | Default | Description |
|---|---|---|
| `FS_FTP_HOST` | — | **Required.** Hostname. |
| `FS_FTP_ROOT` | `/` | Root path on the FTP server. |
| `FS_FTP_USERNAME` | — | **Required.** Username. |
| `FS_FTP_PASSWORD` | — | **Required.** Password. |
| `FS_FTP_PORT` | `21` | Port. |
| `FS_FTP_SSL` | `false` | Enable FTPS. |
| `FS_FTP_TIMEOUT` | `90` | Timeout in seconds. |
| `FS_FTP_PASSIVE` | `true` | Passive mode. |
| `FS_FTP_TRANSFER_MODE` | `FTP_BINARY` | Transfer mode. |
| `FS_FTP_SYSTEM_TYPE` | — | Optional system type hint. |
| `FS_FTP_IGNORE_PASSIVE_ADDRESS` | — | Ignore passive address from server. |
| `FS_FTP_RECURSE_MANUALLY` | `false` | Manual recursive listings. |

**Dropbox**

| Variable | Default | Description |
|---|---|---|
| `FS_DROPBOX_ACCESS_TOKEN` | — | **Required.** Dropbox API access token. |
| `FS_DROPBOX_PREFIX` | `''` | Optional path prefix inside Dropbox. |

---

## Testing

The cleanest way to test code that depends on `FilesystemAdapterInterface` is to use Flysystem's in-memory adapter. It requires no disk I/O and no cleanup, and it implements the full `FilesystemOperator` interface.

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;

final class ReportWriterTest extends TestCase
{
    private FlysystemAdapter $fs;

    #[\Override]
    protected function setUp(): void
    {
        // An in-memory adapter is fast, isolated, and requires no teardown.
        $this->fs = new FlysystemAdapter(
            new Filesystem(new InMemoryFilesystemAdapter()),
        );
    }

    public function testWritesReport(): void
    {
        $writer = new ReportWriter($this->fs);
        $writer->write('2026-05', ['row1', 'row2']);

        $this->assertTrue($this->fs->fileExists('reports/2026-05.csv'));
        $this->assertStringContainsString('row1', $this->fs->read('reports/2026-05.csv'));
    }
}
```

The `InMemoryFilesystemAdapter` is provided by `league/flysystem` core — no extra package is needed. Because `FlysystemAdapter` accepts any `FilesystemOperator`, you can also inject a mock or stub of `FilesystemAdapterInterface` for unit tests that only need to assert calls were made.

For the native `Filesystem` utility class, use a real temporary directory and delete it in `tearDown`. The existing test suite in `tests/Filesystem/FilesystemTest.php` follows exactly this pattern.

---

## Extending

To add a custom storage backend, implement `League\Flysystem\FilesystemAdapter` (the Flysystem adapter interface, not the Altair one):

```php
<?php

declare(strict_types=1);

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

final class RedisFilesystemAdapter implements FilesystemAdapter
{
    public function __construct(private readonly \Redis $redis) {}

    public function write(string $path, string $contents, Config $config): void
    {
        $this->redis->set($path, $contents);
    }

    // … implement read, delete, listContents, etc.
}
```

Then wrap it in `FlysystemAdapter`:

```php
$fs = new FlysystemAdapter(new Filesystem(new RedisFilesystemAdapter($redis)));
```

To wire it through the container, create a configuration class:

```php
<?php

declare(strict_types=1);

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use League\Flysystem\FilesystemAdapter;

final class RedisAdapterConfiguration implements ConfigurationInterface
{
    public function apply(Container $container): void
    {
        $container
            ->delegate(RedisFilesystemAdapter::class, static fn (): RedisFilesystemAdapter =>
                new RedisFilesystemAdapter($container->make(\Redis::class)),
            )
            ->alias(FilesystemAdapter::class, RedisFilesystemAdapter::class);
    }
}
```

Consult the [Flysystem adapter documentation](https://flysystem.thephpleague.com/docs/architecture/) for the full adapter interface contract, including visibility handling and metadata methods.

---

## Recipes

### Upload a file to S3 and get a public URL

Your application should resolve the URL immediately after writing so callers can persist the URL without a second request.

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use League\Flysystem\Visibility;

function uploadAvatar(FlysystemAdapter $fs, string $userId, string $imageData): string
{
    $path = sprintf('avatars/%s.png', $userId);

    $fs->write($path, $imageData, ['visibility' => Visibility::PUBLIC]);

    return $fs->publicUrl($path);
}
```

### Sync a local directory to a remote adapter

Copying a local tree to a remote adapter is a common deployment or backup pattern. Use `Filesystem::listAllFiles` to enumerate sources and `FlysystemAdapter::writeStream` to avoid loading large files into memory.

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use Altair\Filesystem\Filesystem;

function syncToRemote(Filesystem $local, FlysystemAdapter $remote, string $sourceDir, string $targetPrefix): void
{
    foreach ($local->listAllFiles($sourceDir) as $file) {
        $relativePath = ltrim(str_replace($sourceDir, '', $file->getPathname()), '/');
        $remotePath   = $targetPrefix . '/' . $relativePath;

        $stream = fopen($file->getPathname(), 'rb');

        try {
            $remote->writeStream($remotePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
```

### Stream a large file without exhausting memory

Reading a large file with `read()` loads its entire content into a PHP string. Use `readStream` instead and pass the resource directly to the response.

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

function streamFileResponse(
    FlysystemAdapter $fs,
    StreamFactoryInterface $streamFactory,
    ResponseInterface $response,
    string $path,
): ResponseInterface {
    $resource = $fs->readStream($path);
    $stream   = $streamFactory->createStreamFromResource($resource);

    return $response
        ->withHeader('Content-Type', $fs->mimeType($path))
        ->withBody($stream);
}
```

### Atomic log appending

When multiple processes write to the same log file, use `FlysystemAdapter::append()` for simplicity or — if you need true atomicity under concurrency — fall back to `Filesystem::put()` with `LOCK_EX` on a local disk.

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;

// Simple append — no cross-process locking guarantee on all adapters.
function logEvent(FlysystemAdapter $fs, string $message): void
{
    $fs->append('logs/app.log', sprintf('[%s] %s', date('c'), $message));
}
```

For local-disk scenarios where strict locking matters, use `Altair\Filesystem\Filesystem::put($path, $content, true)`, which passes `LOCK_EX` to `file_put_contents`.

### Generate a temporary pre-signed URL

S3 and compatible backends support time-limited URLs. The `temporaryUrl` method is forwarded from `FlysystemAdapter` to the underlying driver.

```php
<?php

declare(strict_types=1);

use Altair\Filesystem\Adapter\FlysystemAdapter;

function generateDownloadLink(FlysystemAdapter $fs, string $path): string
{
    $expiresAt = new DateTimeImmutable('+15 minutes');

    return $fs->temporaryUrl($path, $expiresAt);
}
```

Note that `temporaryUrl` is not part of `FilesystemAdapterInterface`. If your code must be portable across adapters, check the capability before calling it or gate the feature on the adapter type.

---

## Related packages

- **[Container](./container.md)** — the DI container that the `*AdapterConfiguration` classes target. Read this to understand how `delegate` and `alias` work.
- **Http** — if you are building file-download endpoints, combine `readStream` with a PSR-7 response body as shown in the streaming recipe above.
- **Security** — if you need signed download URLs backed by your own key material rather than S3 pre-signing, the Security package's HMAC utilities can sign path tokens.

---

## Migration notes

This package was migrated from Flysystem v1 to v3 during the 2026-05 modernization (Phase 3c). If you have code written against the v1 API, these are the changes you are most likely to encounter:

**Removed adapters.** The following adapters existed in v1 and are gone in v3 with no replacement in this package: Rackspace, Azure Blob Storage, WebDAV, ZipArchive, GridFS, and the cached adapter. Do not attempt to re-add them — Flysystem v3 removed them intentionally.

**`FilesystemInterface` → `FilesystemOperator`.** The `FilesystemAdapterInterface` previously extended `League\Flysystem\FilesystemInterface`. It now extends `League\Flysystem\FilesystemOperator`. Update type hints accordingly.

**No more `__call` magic.** The old `FlysystemAdapter` used `__call` to forward all method calls. The new implementation declares every forwarded method explicitly. If you were relying on any method that is not in the current `FlysystemAdapter`, check whether it still exists in the Flysystem v3 API.

**No caching.** Flysystem v3 removed the `CachedAdapter` from core. `FilesystemAdapterConfiguration` no longer wires one. If you need response caching, wrap the `FilesystemOperator` with a custom caching decorator.

**`fileExists` vs `has` vs `exists`.** Flysystem v3 splits existence checks: `fileExists()` checks files only, `directoryExists()` checks directories only, and `has()` is a convenience alias on `FilesystemOperator`. The Altair `FlysystemAdapter` adds `exists()` on top, which returns `true` for either type.

**`prepend`/`append` semantics.** In v1, these read-modify-write operations were part of the adapter. In v3 they are no longer in Flysystem's core, so `FlysystemAdapter` implements them by reading the current content and writing the combined result. This is not atomic under concurrent access. For local disks, use `Filesystem::put` with locking if you need stronger guarantees.

---

## Limitations

**Visibility semantics differ across adapters.** `'public'` and `'private'` translate to different permission systems depending on the backend. On local disk, visibility maps to POSIX permission bits (configured via `PortableVisibilityConverter`). On S3, it maps to ACLs or bucket policies. Dropbox has its own sharing model. Do not assume that setting `visibility: public` produces the same result on every adapter.

**`prepend` and `append` are not atomic.** Both methods read the current file content and write the concatenated result. Under concurrent writes from multiple processes or requests, the intermediate read can race with another writer. For local-disk use cases where this matters, use `Filesystem::put` with `LOCK_EX`.

**`publicUrl` and `temporaryUrl` are not part of the interface.** These methods exist on `FlysystemAdapter` as concrete forwarding calls but are absent from `FilesystemAdapterInterface`. Code that types against the interface cannot call them. Either type against `FlysystemAdapter` directly, or check adapter capabilities at runtime.

**Caching is not built in.** Flysystem v3 deliberately removed the cached adapter from core. If your application makes many repeated `listContents` or `mimeType` calls against a remote adapter, add a caching layer in your service class rather than at the adapter level.

**No built-in streaming for `prepend`.** `prepend` loads the entire file into memory before writing. For large files, consider a different data design (e.g. write a header file separately and concatenate at read time) rather than prepending to large files in production.
