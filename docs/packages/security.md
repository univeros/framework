# Security

Cryptographic primitives for key derivation, symmetric encryption, and timing-safe MAC verification: the foundation that other Altair packages build on.

**Package:** `univeros/security`
**Namespace:** `Altair\Security`

---

## Introduction

The Security package is deliberately narrow. It provides the cryptographic building blocks that the rest of the Altair framework needs without trying to be a complete application-security solution. If you are looking for a CSRF token class, an authentication layer, or an encrypted-session handler, those live in other packages that depend on this one. What this package ships is the substrate they are built from.

The core of the package is two key-derivation classes: `HkdfKey` and `Pbkdf2Key`. Both implement `KeyInterface`, which declares a single method, `derive(): string`, that returns a raw binary key of whatever length the caller requests. That key is the input to everything else: symmetric encryption, HMAC generation, cookie signing. Having a typed contract for "something that can produce a key" lets the `Encrypter` accept either derivation strategy interchangeably.

`Encrypter` is the only class that performs confidentiality operations. It takes a `KeyInterface` and a cipher name at construction time, derives the key immediately, and validates that the derived key length matches the cipher. Encryption produces a base64-encoded JSON envelope (`iv`, `value`, `mac`). Decryption re-validates the MAC before decrypting, and the MAC comparison in `PayloadValidator` is done via a double-HMAC timing-safe pattern using `hash_equals`.

`Salt` is a small utility class that generates URL-safe random strings suitable for use as HKDF salts or Pbkdf2 salts. It uses `random_bytes` internally, so its output is cryptographically random. It is not a replacement for a salt stored alongside a password hash; it is a convenience for generating per-operation salts.

This package does not ship password hashing (`password_hash` / `password_verify`), asymmetric cryptography, token signing (JWT is in `Altair\Http`), or CSRF tokens. The CSRF token class lives in `Altair\Session\CsrfToken`, which uses `Altair\Security\Support\Salt` to produce a random value and `hash` + `hash_equals` for verification. That cross-package dependency is the intended usage pattern: Session owns the token lifecycle; Security owns the randomness primitive.

---

## Installation

Install via Composer:

```bash
composer require univeros/security
```

The only declared runtime requirement beyond PHP 8.3 is `ext-openssl`, which `Encrypter` needs. The key-derivation classes (`HkdfKey`, `Pbkdf2Key`) and `Salt` use only functions from PHP's always-bundled `hash` extension (`hash_hmac`, `hash_pbkdf2`, `hash_equals`, `random_bytes`). You do not need to require `ext-hash` separately; it is compiled into every standard PHP distribution. If your deployment uses a stripped PHP build, verify that `ext-openssl` is present before constructing `Encrypter`.

If you are consuming the full `univeros/framework` monorepo, `univeros/security` is satisfied through the root `replace` map; no separate `require` is needed.

---

## Quick start

The most common first use of this package is constructing an encrypter from a high-entropy application key using PBKDF2 to derive a correctly-sized AES key.

```php
<?php

declare(strict_types=1);

use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Encrypter;
use Altair\Security\Support\Pbkdf2Key;
use Altair\Security\Support\Salt;

// Generate a fresh per-operation salt with Salt.
// Store $salt alongside anything encrypted with this key.
$salt = (new Salt())->generate(48);

// Derive a 32-byte AES-256-CBC key from the application secret.
// 100 000 iterations is the Pbkdf2Key default.
$key = new Pbkdf2Key(
    key: $_ENV['APP_KEY'],
    salt: $salt,
    length: EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH,
);

// Construct the encrypter. It validates key length against the cipher immediately.
$encrypter = new Encrypter($key, EncrypterInterface::AES_256_CBC_CIPHER);

// Encrypt any PHP value. The result is a base64-encoded JSON envelope.
$payload = $encrypter->encrypt(['user_id' => 42, 'role' => 'editor']);

// Decrypt. MAC is verified before the ciphertext is touched.
$data = $encrypter->decrypt($payload);
// $data === ['user_id' => 42, 'role' => 'editor']
```

---

## Concepts

### HKDF vs. PBKDF2

Both classes derive a fixed-length binary key from a secret input, but they solve different problems.

**PBKDF2** (Password-Based Key Derivation Function 2, RFC 2898) is designed to be slow. Its `$iterations` parameter controls how many times the underlying HMAC is applied. The default in `Pbkdf2Key` is 100,000 iterations. That cost is the entire point: it makes brute-force attacks against low-entropy inputs (such as user-chosen passwords) computationally expensive. Use `Pbkdf2Key` when your input key is a password or any other value a human might have typed.

**HKDF** (HMAC-based Key Derivation Function, RFC 5869) is designed to be fast. It is a two-step extract-then-expand construction. The extract step condenses the input key material (IKM) and salt into a pseudorandom key (PRK) with one HMAC call. The expand step stretches the PRK into the required number of output bytes using a short loop of HMAC calls, mixing in an optional `$context` string (labelled "info" in the RFC) at each block. Use `HkdfKey` when your input is already high-entropy (for example, a random application secret or a key returned by a secret manager) and you need to derive one or more purpose-specific subkeys from it. Pass a distinct `$context` string for each purpose.

Both classes use SHA-256 as the underlying hash algorithm, inherited from `AbstractKey::$algorithm`. Neither class exposes a method to change the algorithm; if you need a different digest, you would subclass `AbstractKey` and override `$algorithm`. That said, SHA-256 is an appropriate default for both constructions.

### The encryption envelope

`Encrypter::encrypt` serializes the value with `serialize`, encrypts it with `openssl_encrypt` using the derived key and a freshly generated random IV (`random_bytes(16)`), computes an HMAC-SHA-256 MAC over the base64-encoded IV concatenated with the ciphertext, and returns the whole structure as a base64-encoded JSON string with three keys: `iv`, `value`, and `mac`.

`Encrypter::decrypt` reverses that process. Before decrypting, `PayloadValidator::validate` checks that all three keys are present and that the MAC is correct. The MAC comparison uses a double-HMAC technique: both the stored MAC and the freshly computed MAC are re-HMACed under the same ephemeral `random_bytes(16)` key, and only then compared with `hash_equals`. This neutralizes timing side-channels in the MAC comparison itself.

Note that `decrypt` calls `unserialize` with `['allowed_classes' => true]`. Be aware of PHP object injection risks if you decrypt data from an untrusted source. The MAC check prevents a tampered ciphertext from reaching `unserialize`, but you should never decrypt data whose provenance you do not control.

### Salt generation

`Salt::generate(int $length = 32): string` calls `random_bytes($length)`, base64-encodes the result, translates `+`, `/`, and `=` to `_`, `-`, and `.` (URL-safe alphabet), and trims to exactly `$length` characters. The output is URL-safe and of predictable length, but note that because it is derived from `ceil($length * 3 / 4) * 4 / 3` bytes of randomness and then sliced, the effective entropy is slightly below `$length` bytes; it is still fully unpredictable, but you should not treat the character count as equivalent to the byte count of `random_bytes`.

---

## Usage

### PBKDF2 key derivation

Use `Pbkdf2Key` when the source secret has low entropy, such as a user-supplied password.

```php
use Altair\Security\Support\Pbkdf2Key;
use Altair\Security\Contracts\EncrypterInterface;

// $salt must be stored alongside the ciphertext so decryption can reproduce
// the exact same key. Generate it once with Salt::generate() and persist it.
$key = new Pbkdf2Key(
    key: $password,
    salt: $storedSalt,
    length: EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH, // 16 bytes
    iterations: 200_000, // default is 100 000; raise for higher-value data
);

$rawKey = $key->derive(); // returns a binary string of exactly $length bytes
```

The `$salt` argument is required for `Pbkdf2Key`. Passing an empty string is technically valid but eliminates the salt's purpose; always supply a randomly generated salt. The `$iterations` default of 100,000 reflects 2020-era guidance; consider raising it on hardware where latency permits.

If `hash_pbkdf2` returns `false` (which in practice means the algorithm name is invalid), `derive` throws `InvalidConfigException`. Since SHA-256 is hard-coded as the algorithm, this exception path is only reachable if you subclass and override `$algorithm` with a name PHP does not recognize.

`Pbkdf2Key` implements `\Stringable`, so you can cast it to a string and it will call `derive()` for you. Use this sparingly; calling `derive()` computes the full PBKDF2 iteration chain each time, and repeated calls are not cached.

### HKDF key derivation

Use `HkdfKey` when the source key material is already high-entropy and you need to derive one or more purpose-bound subkeys from it.

```php
use Altair\Security\Support\HkdfKey;
use Altair\Security\Contracts\EncrypterInterface;

// Derive a 32-byte subkey for cookie signing, bound to the 'cookie-signing' context.
$cookieKey = new HkdfKey(
    key: $_ENV['APP_KEY'],
    salt: null,       // null defaults to a zero-byte string of hashLength bytes (per RFC 5869)
    context: 'cookie-signing',
    length: EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH,
);

// Derive a separate subkey for session encryption from the same master secret.
$sessionKey = new HkdfKey(
    key: $_ENV['APP_KEY'],
    salt: null,
    context: 'session-encryption',
    length: EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH,
);

$rawCookieKey   = $cookieKey->derive();
$rawSessionKey  = $sessionKey->derive();
```

`HkdfKey`'s constructor validates the `$length` argument: it must be between 0 and `255 * hashLength` (for SHA-256 that is `255 * 32 = 8160` bytes). A length of 0 returns exactly one HMAC block (32 bytes for SHA-256). Any other length is rounded up to the nearest block boundary during the expand loop, then trimmed to the exact requested length.

When `$salt` is `null`, the implementation substitutes a zero-filled byte string of the same length as the hash output (`str_repeat("\0", $hashLength)`), which matches the RFC 5869 default for the extract step. This is safe, but providing a non-null random salt adds an extra layer of domain separation when you share the same master key across multiple deployments.

The `$context` parameter maps to the RFC 5869 "info" field. It is concatenated into each expand-step HMAC call alongside the block counter. Changing `$context` while holding every other parameter constant produces a completely different output key, which is the entire point.

**Test vector compliance.** `HkdfKeyTest` verifies the implementation against all three SHA-256 test vectors from RFC 5869 Appendix A (A.1, A.2, A.3). The computed output matches the expected OKM values byte-for-byte.

### Symmetric encryption and decryption

`Encrypter` requires a `KeyInterface` instance and one of the three cipher constants declared on `EncrypterInterface`. The cipher and key length must be compatible; the constructor enforces this.

```php
use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Encrypter;
use Altair\Security\Support\HkdfKey;

// AES-128-CBC requires a 16-byte key; AES-192-CBC requires 24; AES-256-CBC requires 32.
$key = new HkdfKey(
    key: $_ENV['APP_KEY'],
    salt: null,
    context: 'data-at-rest',
    length: EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH,
);

$encrypter = new Encrypter($key, EncrypterInterface::AES_256_CBC_CIPHER);

// Encrypt. Throws EncryptException on OpenSSL failure.
$payload = $encrypter->encrypt($value);

// Decrypt. Throws DecryptException if the MAC is wrong or the payload is malformed.
$value = $encrypter->decrypt($payload);
```

`encrypt` accepts any PHP value because it passes the input through `serialize` first. Scalars, arrays, and objects are all valid. `decrypt` calls `unserialize` with `['allowed_classes' => true]`, which permits reconstructing objects. Only decrypt data you encrypted yourself, or data whose integrity you have independently verified, because `unserialize` with class instantiation enabled can trigger constructors on arbitrary classes if the serialized string is attacker-controlled.

The HMAC key used for the MAC is the same derived key used for encryption. If you need separate encryption and MAC keys (a common hardening pattern), derive two keys with `HkdfKey` using distinct `$context` strings and use one for the `Encrypter` and the other for an external HMAC.

### Computing and verifying a MAC

You can use the `hash` method on `Encrypter` to compute an HMAC-SHA-256 over arbitrary `iv + data` input using the same derived key.

```php
// Compute a hex MAC.
$mac = $encrypter->hash($iv, $data);

// Compute a raw-binary MAC.
$rawMac = $encrypter->hash($iv, $data, raw: true);
```

`PayloadValidator` uses this internally with a double-HMAC pattern to ensure the comparison itself leaks no timing information:

1. A fresh `random_bytes(16)` value is generated.
2. Both the stored MAC and the freshly computed MAC are wrapped in a second HMAC call under that random key.
3. `hash_equals` compares the two wrapped values.

Because the comparison inputs are themselves random HMAC outputs of equal length, `hash_equals`'s constant-time property holds even if an attacker can observe request latency.

### Generating a random salt

`Salt::generate` is the entry point for all random-value needs in the package. Use it wherever you need a URL-safe random string of a specific byte length.

```php
use Altair\Security\Support\Salt;

$salt = new Salt();

// 32-character URL-safe string (the default).
$s32 = $salt->generate();

// 48-character URL-safe string, suitable as a PBKDF2 salt.
$s48 = $salt->generate(48);
```

The output characters are drawn from the set `[A-Za-z0-9_\-.]`. The `generate` call can throw `\Exception` if `random_bytes` fails; this happens only under exceptional OS entropy conditions and you should let it propagate.

---

## Configuration

The Security package has no `Configuration/` directory and no configuration classes. There are no environment variables it reads by default. All parameters (key material, salt values, cipher choice, iteration counts) are passed explicitly through constructors.

If you want to wire a shared `Encrypter` through the container using an application key stored in `.env`, that wiring belongs in your own configuration class. A minimal example:

```php
<?php

declare(strict_types=1);

namespace App\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Support\Env;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Encrypter;
use Altair\Security\Support\HkdfKey;

class EncrypterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    #[\Override]
    public function apply(Container $container): void
    {
        $appKey = $this->env->get('APP_KEY');
        if ($appKey === null || strlen($appKey) < 32) {
            throw new \RuntimeException('APP_KEY must be at least 32 characters.');
        }

        $container->delegate(
            EncrypterInterface::class,
            fn (): Encrypter => new Encrypter(
                new HkdfKey($appKey, null, 'data-at-rest', EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH),
                EncrypterInterface::AES_256_CBC_CIPHER,
            ),
        );
    }
}
```

---

## Testing

Security code divides into two categories when it comes to testing: shape correctness (which is straightforward) and actual cryptographic security (which requires external evaluation, not unit tests). The test suite covers the former.

**Test vectors.** The most valuable tests in this package are the ones that compare derived-key output against published RFC test vectors. `HkdfKeyTest` verifies three vectors from RFC 5869 Appendix A. `Pbkdf2KeyTest` verifies three vectors from RFC 6070. Passing these tests gives strong confidence that the implementations are correct for the inputs described in the standard.

```php
use Altair\Security\Support\HkdfKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HkdfKeyTest extends TestCase
{
    public static function dataProvider(): array
    {
        return [
            // RFC 5869 Appendix A.1
            [
                'ikm'  => '0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b',
                'salt' => '000102030405060708090a0b0c',
                'info' => 'f0f1f2f3f4f5f6f7f8f9',
                'l'    => 42,
                'okm'  => '3cb25f25faacd57a90434f64d0362f2a2d2d0a90cf1a5a4c5db02d56ecc4c5bf34007208d5b887185865',
            ],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testHkdf(string $ikm, string $salt, string $info, int $l, string $okm): void
    {
        $derived = (new HkdfKey(hex2bin($ikm), hex2bin($salt), hex2bin($info), $l))->derive();

        $this->assertSame($okm, bin2hex($derived));
    }
}
```

**Shape tests for the encrypter.** Test that encrypt/decrypt round-trips correctly, that a mismatched context key cannot decrypt a payload, and that a tampered payload throws `DecryptException`.

```php
use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Encrypter;
use Altair\Security\Exception\DecryptException;
use Altair\Security\Support\HkdfKey;
use PHPUnit\Framework\TestCase;

class EncrypterTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $key = new HkdfKey('test-key', null, null, EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH);
        $e   = new Encrypter($key, EncrypterInterface::AES_256_CBC_CIPHER);

        $this->assertSame('hello', $e->decrypt($e->encrypt('hello')));
    }

    public function testContextMismatchThrows(): void
    {
        $keyA = new HkdfKey('test-key', null, 'context-a', EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH);
        $keyB = new HkdfKey('test-key', null, 'context-b', EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH);

        $payload = (new Encrypter($keyA, EncrypterInterface::AES_256_CBC_CIPHER))->encrypt('secret');

        $this->expectException(DecryptException::class);
        (new Encrypter($keyB, EncrypterInterface::AES_256_CBC_CIPHER))->decrypt($payload);
    }
}
```

**What tests cannot tell you.** Shape tests confirm correct wiring but cannot confirm that AES-CBC with HMAC-SHA-256 is the right construction for your threat model, that your iteration count is high enough, or that your salt storage is secure. For those questions, consult an independent cryptographic review.

The test suite lives under `tests/Security/` and mirrors the `src/Altair/Security/` layout:

```
tests/Security/
    EncrypterTest.php
    HkdfKeyTest.php
    Pbkdf2KeyTest.php
    SaltTest.php
```

---

## Extending

**Do not extend `HkdfKey` or `Pbkdf2Key` to swap in a different cryptographic algorithm** unless you have a concrete, reviewed reason to do so. The algorithm choice (SHA-256) is embedded in `AbstractKey::$algorithm` and inherited by both subclasses. Overriding it without understanding how the change propagates through HKDF's extract and expand steps, or through PBKDF2's iteration loop, risks silent security degradation.

If you need a different key derivation primitive entirely (for example, Argon2id for password hashing), implement `KeyInterface` from scratch rather than subclassing `AbstractKey`. `KeyInterface` declares a single method (`derive(): string`) and imposes no assumptions about the underlying algorithm.

`EncrypterInterface` is similarly open for alternative implementations. The three cipher constants on the interface (`AES_128_CBC_CIPHER`, `AES_192_CBC_CIPHER`, `AES_256_CBC_CIPHER`) and the `HASH_SHA256_ALGORITHM` constant are purely informational; an alternative implementation could use a different cipher as long as it returns a base64-encoded string from `encrypt` that its own `decrypt` can reverse.

---

## Recipes

### Deriving purpose-bound subkeys from a single master secret

HKDF is the right tool when you have one high-entropy master key and need several independent subkeys for different functions. A different `$context` value produces a completely independent output.

```php
use Altair\Security\Support\HkdfKey;
use Altair\Security\Contracts\EncrypterInterface;

$masterKey = $_ENV['APP_KEY'];

// Each subkey is independent even though the master key and salt are the same.
$encryptionKey = (new HkdfKey($masterKey, null, 'encryption', EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH))->derive();
$signingKey    = (new HkdfKey($masterKey, null, 'signing',    EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH))->derive();
$cookieKey     = (new HkdfKey($masterKey, null, 'cookie',     EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH))->derive();
```

Never use the raw `$masterKey` directly as a cipher key. Deriving subkeys with HKDF limits the blast radius if one subkey is ever compromised.

### Deriving an encryption key from a user password

When the key material is user-supplied and therefore low-entropy, use `Pbkdf2Key`. Generate and store the salt alongside the encrypted record so you can reproduce the same key for decryption.

```php
use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Encrypter;
use Altair\Security\Support\Pbkdf2Key;
use Altair\Security\Support\Salt;

// On first write: generate a salt and store it.
$salt      = (new Salt())->generate(48);
$key       = new Pbkdf2Key($userPassword, $salt, EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH);
$encrypter = new Encrypter($key, EncrypterInterface::AES_256_CBC_CIPHER);
$payload   = $encrypter->encrypt($sensitiveValue);

// Persist: ['salt' => $salt, 'payload' => $payload]

// On read: reconstruct the same key using the stored salt.
$key       = new Pbkdf2Key($userPassword, $row['salt'], EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH);
$encrypter = new Encrypter($key, EncrypterInterface::AES_256_CBC_CIPHER);
$value     = $encrypter->decrypt($row['payload']);
```

This pattern does not replace a proper password hash for authentication (`password_hash` / `password_verify`). Use `Pbkdf2Key` for key derivation; use PHP's native password functions for verifying that a user knows their password.

### Integrating with the CSRF middleware

The CSRF token class lives in `Altair\Session\CsrfToken`. It uses `Altair\Security\Support\Salt` to generate the random token value, then hashes it with SHA-512, and uses `hash_equals` for timing-safe comparison. The Security package is a dependency of Session, not the other way around; you do not interact with Security directly for CSRF.

Wire `CsrfMiddleware` from `Altair\Http\Middleware` into your PSR-15 pipeline with a `SessionManager` that exposes a `CsrfToken`. The middleware handles the full lifecycle: it validates the token on unsafe HTTP methods (POST, PUT, PATCH, DELETE) and injects a hidden `_csrf` input field into HTML form responses.

```php
use Altair\Http\Middleware\CsrfMiddleware;

// In your middleware queue (assembled before Relay dispatches):
// The middleware injects <input type="hidden" name="_csrf" ...>
// into every POST form in HTML responses.
$queue[] = new CsrfMiddleware($sessionManager, $mimeType, $responseFactory);
```

See [session.md](./session.md) for how to configure `SessionManager` and its backing `SessionBlockInterface`.

### Rotating the application key

When you rotate `APP_KEY`, all previously encrypted payloads become permanently unreadable because the derived key changes. Before rotating:

1. Decrypt all stored payloads with the old key.
2. Re-encrypt them with a new `Encrypter` built from the new key.
3. Replace `APP_KEY` in your deployment environment.

HKDF makes this particularly important: even changing `$context` while keeping the same `APP_KEY` produces a different key. Treat `$context` as a stable per-purpose constant, not a rotation mechanism.

### Verifying a payload MAC without full decryption

If you need to authenticate a payload without decrypting it (for example, in an early-exit middleware), instantiate a `PayloadValidator` directly.

```php
use Altair\Security\Validator\PayloadValidator;

$decoded = json_decode(base64_decode($payload), true);

if (!(new PayloadValidator($encrypter, $decoded))->validate()) {
    // MAC is invalid or payload structure is wrong. Reject early.
}
```

`PayloadValidator::validate` checks that `iv`, `value`, and `mac` are all present, then performs the double-HMAC timing-safe comparison. It throws `\Exception` (from `random_bytes`) only under OS entropy failure.

---

## Related packages

- [session.md](./session.md): `Altair\Session\CsrfToken` is the CSRF token implementation. It depends on `Altair\Security\Support\Salt` for random value generation and uses `hash_equals` for timing-safe comparison. Configure session storage and the `SessionManager` here.
- [http.md](./http.md): `Altair\Http\Middleware\CsrfMiddleware` is the PSR-15 middleware that enforces CSRF protection in the request pipeline. It depends on `SessionManager::getCsrfToken()`.
- [cookie.md](./cookie.md): Cookie value objects live here. Signed cookies (if your application needs them) would use a key derived by `HkdfKey` or `Pbkdf2Key` from this package; the Security package does not ship a signing helper for cookies itself.
- [configuration.md](./configuration.md): Use `EnvironmentConfiguration` and `EnvAwareTrait` to load `APP_KEY` from `.env` and wire it into a shared `Encrypter` binding through the container.

---

## Migration notes

### Phase 1 modernization (2026-05)

The Security package's `composer.json` was updated in Phase 1 to declare `php: >=8.3` and `ext-openssl: *`. The `ext-hash` extension is not listed explicitly because it is compiled into every standard PHP build and was never separately declarable. No cryptographic behaviour changed; the update was entirely manifest and tooling hygiene.

No class in this package uses `Zend\Diactoros\*` or any other dependency replaced in Phase 1. No contracts changed. Previously compiled payloads remain decryptable with the same key and cipher.

---

## Limitations

**This package is cryptographic primitives, not a complete security solution.** Do not treat having `univeros/security` as equivalent to having a security-reviewed application. In particular:

- **No authenticated encryption (AEAD).** `Encrypter` uses AES-CBC with a separate HMAC-SHA-256 MAC (Encrypt-then-MAC). This is a sound construction, but it is not AES-GCM. If you need an AEAD cipher with built-in authentication, consider a library that wraps `libsodium` directly, such as `paragonie/halite` or `defuse/php-encryption`.
- **No Argon2 / bcrypt password hashing.** `Pbkdf2Key` is not a password storage function. For storing user passwords, use PHP's native `password_hash()` with `PASSWORD_ARGON2ID`. Use `Pbkdf2Key` only for key derivation from a known secret (including a password that has already been validated by `password_verify`).
- **No asymmetric cryptography.** There is no RSA, ECDSA, or X25519 in this package. JWT signing and verification live in `Altair\Http`.
- **No secret manager integration.** Key material arrives as plain strings. Wrapping `APP_KEY` retrieval in a proper secret manager is the application's responsibility.
- **AES-CBC requires a unique IV per encryption.** `Encrypter::encrypt` generates a new IV with `random_bytes(16)` on every call, which is correct. Never reuse an IV for AES-CBC with the same key; doing so leaks information about the plaintext.
- **`unserialize` in `decrypt`.** The MAC check prevents an attacker from submitting a crafted payload. However, if you ever call `decrypt` on data you did not encrypt yourself, you accept the full PHP object injection risk. The `['allowed_classes' => true]` option does not restrict which classes can be instantiated during deserialization.
- **No high-level encrypted-data API.** If you need to encrypt files, blobs, or data larger than a single PHP value, use `paragonie/halite` (libsodium-based) or `defuse/php-encryption` (OpenSSL-based with a cleaner high-level API). Both libraries provide key management, nonce handling, and chunked encryption that this package does not address.
