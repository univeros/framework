<?php
namespace Altair\Tests\Security;

use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Encrypter;
use Altair\Security\Exception\DecryptException;
use Altair\Security\Support\HkdfKey;
use Altair\Security\Support\Pbkdf2Key;
use Altair\Security\Support\Salt;

class EncrypterTest extends \PHPUnit_Framework_TestCase
{
    public function testEncryptionWithHkdfKeyAndAES128CBCCipher()
    {
        $key = new HkdfKey('test-key', null, null, EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_128_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithHkdfKeyAndAES128CBCCipherWithContext()
    {
        $key = new HkdfKey('test-key', null, 'test-context', EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_128_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithHkdfKeyAndAES128CBCCipherWithSalt()
    {
        $salt = (new Salt())->generate(12);
        $key = new HkdfKey('test-key', $salt, null, EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_128_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithHkdfKeyAndAES128CBCCipherWithSaltAndContext()
    {
        $salt = (new Salt())->generate(12);
        $key = new HkdfKey('test-key', $salt, 'test-context', EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_128_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithHkdfKeyAndAES192CBCCipher()
    {
        $key = new HkdfKey('test-key', null, null, EncrypterInterface::AES_192_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_192_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithHkdfKeyAndAES192CBCCipherWithContext()
    {
        $key = new HkdfKey('test-key', null, 'test-context', EncrypterInterface::AES_192_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_192_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testFailedEncryptionWithHkdfKeyAndAES192CBCCipherWithDifferentContext()
    {
        $key = new HkdfKey('test-key', null, 'test-context', EncrypterInterface::AES_192_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_192_CBC_CIPHER);

        $noContextKey = new HkdfKey('test-key', null, null, EncrypterInterface::AES_192_CBC_CIPHER_KEY_LENGTH);
        $ee = new Encrypter($noContextKey, EncrypterInterface::AES_192_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->expectException(DecryptException::class);
        $this->expectExceptionMessage('Payload structure or MAC is invalid');
        $this->assertEquals('foo', $ee->decrypt($encrypted));
    }

    public function testEncryptionWithHkdfKeyAndAES256CBCCipher()
    {
        $key = new HkdfKey('test-key', null, null, EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_256_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithPbkdf2KeyAndAES128CBCCipher()
    {
        $key = new Pbkdf2Key('test-key', 'secret', EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_128_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithPbkdf2KeyAndAES128CBCCipherWithSalt()
    {
        $salt = (new Salt())->generate(48);
        $key = new Pbkdf2Key('test-key', $salt, EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_128_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithPbkdf2KeyAndAES192CBCCipher()
    {
        $salt = base64_encode(random_bytes(10));
        $key = new Pbkdf2Key('test-key', $salt, EncrypterInterface::AES_192_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_192_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }

    public function testEncryptionWithPbkdf2KeyAndAES256CBCCipher()
    {
        $salt = base64_encode(random_bytes(10));
        $key = new Pbkdf2Key('test-key', $salt, EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH);
        $e = new Encrypter($key, EncrypterInterface::AES_256_CBC_CIPHER);

        $encrypted = $e->encrypt('foo');
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', $e->decrypt($encrypted));
    }
}
