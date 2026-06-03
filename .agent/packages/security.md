# univeros/security  ·  Altair\Security

**Purpose:** Cryptographic primitives for key derivation (HKDF, PBKDF2), symmetric encryption, and timing-safe MAC verification.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `EncrypterInterface` | `decrypt(string)` | `mixed` | constants: `AES_128_CBC_CIPHER`, `AES_128_CBC_CIPHER_KEY_LENGTH`, `AES_192_CBC_CIPHER`, `AES_192_CBC_CIPHER_KEY_LENGTH`, `AES_256_CBC_CIPHER`, `AES_256_CBC_CIPHER_KEY_LENGTH`, `BLOCK_SIZE`, `HASH_SHA256_ALGORITHM` |
|  | `encrypt(mixed)` | `string` |  |
|  | `hash(string, string, bool)` | `string` |  |
| `KeyInterface` | `derive()` | `string` |  |

## Concrete classes

- `AbstractKey` _(abstract)_ — implements `KeyInterface`, `Stringable`
- `Encrypter` — implements `EncrypterInterface`
- `HkdfKey` — implements `KeyInterface`, `Stringable`
- `PayloadValidator`
- `Pbkdf2Key` — implements `KeyInterface`, `Stringable`
- `Salt`

## Tests as documentation

- `tests/Security/EncrypterTest.php`
- `tests/Security/HkdfKeyTest.php`
- `tests/Security/Pbkdf2KeyTest.php`
- `tests/Security/SaltTest.php`
