# Cookie

> Immutable value objects for HTTP cookies, plus a small manager that reads, writes, and modifies them on PSR-7 requests and responses.

**Composer:** `univeros/cookie`
**Namespace:** `Altair\Cookie`

## Introduction

PSR-7 leaves cookies as raw strings on the `Cookie` and `Set-Cookie` headers. That is fine when you only need to read a value, but it gets awkward the moment you want to round-trip a cookie through middleware, attach attributes like `Domain` or `Max-Age`, or replace one cookie inside a response without touching the others. You end up parsing strings by hand and praying you matched the spec.

This package gives you two `readonly` value objects — `Cookie` for what arrives on the request, `SetCookie` for what you send back on the response — and a `CookieManager` that bridges them to PSR-7 message instances. Reading a cookie returns a typed object. Setting one merges into the existing `Set-Cookie` header set without clobbering siblings. Modifying one is a `callable` you hand to the manager, which keeps the immutability guarantee end-to-end.

The value objects are `final readonly`, so a `Cookie` you obtained from middleware A cannot be mutated by middleware B. Every change produces a new instance via `withFoo()`. The manager itself holds no state — it is a thin convenience layer over the factories and collections, and you can drop down to those any time you need finer control (e.g. building an entire `CookieCollection` from a parsed header before injecting it once).

What this package deliberately does *not* do: it does not sign or encrypt cookie values, it does not impose a session contract, and it does not store cookies on the server. Signed/encrypted cookies belong in [security.md](./security.md); server-side session storage lives in [session.md](./session.md). Cookies, here, are only the wire format.

## Installation

Standalone:

```bash
composer require univeros/cookie
```

The package needs no PHP extensions beyond what `psr/http-message` already requires. It pulls in `univeros/structure` because `CookieCollection` and `SetCookieCollection` extend the framework's `Map` type — see [structure.md](./structure.md) for the collection API you inherit.

If you are installing the full framework, `composer require univeros/framework` already includes this package.

## Quick start

The smallest useful flow: read a cookie off an incoming request, set a fresh one on the outgoing response.

```php
use Altair\Cookie\CookieManager;
use Altair\Cookie\SetCookie;

$manager = new CookieManager();

$theme = $manager->getFromRequest($request, 'theme', 'light')->getValue();

$response = $manager->setOnResponse(
    $response,
    (new SetCookie('last_seen', (string) time()))
        ->withPath('/')
        ->withHttpOnly(true)
        ->withSecure(true),
);
```

`getFromRequest()` always returns a `Cookie` — if the header had no `theme`, you get a fresh one populated with the fallback value you passed (`'light'`). On the response side, `setOnResponse()` merges the new `SetCookie` into the existing `Set-Cookie` header set, replacing any prior entry with the same name and leaving the rest alone.

## Concepts

The package has four kinds of moving parts:

- **`Cookie`** — a single request-side cookie. Name plus value. That is the entire shape: there is no `Domain` or `Path` on a request cookie because browsers do not send those.
- **`SetCookie`** — a single response-side cookie. Name, value, and the six attributes the `Set-Cookie` header allows (`Expires`, `Max-Age`, `Path`, `Domain`, `Secure`, `HttpOnly`). Both classes extend `AbstractCookie`, which is itself `abstract readonly`.
- **`CookieCollection` / `SetCookieCollection`** — keyed collections of cookies, extending `Altair\Structure\Map`. Use these when you need to handle many cookies at once or pass a header value through several transforms before re-serializing.
- **`CookieManager`** — the high-level API. Stateless, instance-based, and the place to start unless you have a specific reason to drop down to the factories.

The lifecycle goes one of two directions:

```
Request header  →  CookieFactory      →  CookieCollection      →  Cookie
Response header →  SetCookieFactory   →  SetCookieCollection   →  SetCookie
```

Going back out, each collection knows how to serialize itself into the right header (`injectIntoRequestHeader` for `Cookie`, `injectIntoResponseHeader` for `Set-Cookie`). The manager handles both directions for you.

## Usage

### Creating cookies

`Cookie` is name-plus-value, and that is it.

```php
use Altair\Cookie\Cookie;

$cookie = new Cookie('session_id', 'abc123');
$cookie->getName();           // 'session_id'
$cookie->getValue();          // 'abc123'
(string) $cookie;             // 'session_id=abc123' (urlencoded)
```

`SetCookie` carries the attributes the browser persists. Construct positionally if you have every value up front, or chain `with*` methods for clarity.

```php
use Altair\Cookie\SetCookie;

$setCookie = (new SetCookie('remember_me', 'token-xyz'))
    ->withPath('/')
    ->withDomain('.example.com')
    ->withSecure(true)
    ->withHttpOnly(true)
    ->withMaxAge(60 * 60 * 24 * 30);
```

`withExpires()` accepts any of `int` (Unix timestamp), `DateTimeInterface`, `string` (anything `strtotime()` parses), or `null` to clear. The first form is the most predictable; reach for the string form only when you are passing through a value you read from a header.

> Gotcha: `withSecure(null)` resolves to `false`, not "keep current". The parameter is nullable for API symmetry, but a null argument is coerced. If you want to leave the flag alone, do not call `withSecure()` at all.

### Reading cookies from a request

The manager hides the header lookup and string parsing.

```php
$theme = $manager->getFromRequest($request, 'theme');
$theme->getValue(); // null if the cookie was not present
```

If you want a default for missing cookies, pass it as the third argument — the manager returns a fresh `Cookie` populated with it rather than `null`.

```php
$theme = $manager->getFromRequest($request, 'theme', 'light');
```

For bulk reads, skip the manager and use the factory directly:

```php
use Altair\Cookie\Factory\CookieFactory;

$cookies = CookieFactory::createCollectionFromRequest($request);

foreach ($cookies as $name => $cookie) {
    // $cookie is a Cookie instance keyed by name
}
```

The collection is an `Altair\Structure\Map`, so `->hasKey()`, `->get()`, `->remove()`, iteration, and the rest of the Map API all work.

### Writing cookies to a response

`setOnResponse()` replaces the named cookie if it exists, appends it if it does not, and leaves all other `Set-Cookie` headers in place.

```php
$response = $manager->setOnResponse(
    $response,
    new SetCookie('locale', 'en-GB'),
);
```

To remove a cookie from the response builder before sending (different from telling the browser to forget it), use `removeFromResponse()`:

```php
$response = $manager->removeFromResponse($response, 'locale');
```

To tell the *browser* to forget a cookie, send back one with an expiry in the past — `expireOnResponse()` does this in one call.

```php
$response = $manager->expireOnResponse($response, 'remember_me');
```

### Modifying cookies in place

Often you do not want to read-then-write; you want to transform whatever is on the message. `modifyOnRequest()` and `modifyOnResponse()` take a callable that receives the current value (or a fresh one if missing) and returns the replacement.

```php
$response = $manager->modifyOnResponse(
    $response,
    'cart',
    static fn (SetCookie $cookie): SetCookie => $cookie
        ->withValue($newCartId)
        ->withMaxAge(3600)
        ->withHttpOnly(true),
);
```

The callable signature stays the same on the request side; you just receive a `Cookie` instead of a `SetCookie`.

### Parsing raw header strings

When you have a header string rather than a PSR-7 message — for instance, you are testing a parser or proxying values — the factories accept raw input.

```php
use Altair\Cookie\Factory\CookieFactory;
use Altair\Cookie\Factory\SetCookieFactory;

$cookie    = CookieFactory::createFromPairString('theme=light');
$setCookie = SetCookieFactory::createFromCookieString(
    'LSID=DQAAAK%2FEaem_vYg; Path=/accounts; Expires=Wed, 13 Jan 2021 22:23:01 GMT; Secure; HttpOnly'
);
```

Both factories urldecode values on parse and urlencode them again on `__toString`, so a round trip is lossless for the byte ranges allowed in cookie values.

## Configuration

The package itself has no `Configuration` class — there is nothing to wire. `CookieManager` is a plain class with no constructor arguments, so the container can construct it on demand without registration. If you want a shared instance, declare it in your container bindings:

```php
use Altair\Container\Container;
use Altair\Cookie\CookieManager;

$container->share(CookieManager::class);
```

Anywhere downstream that type-hints `CookieManager` will then receive the same instance.

## Testing

The value objects compare by value, so PHPUnit's `assertEquals` is the right assertion when you want to check shape rather than identity.

```php
use Altair\Cookie\Cookie;
use PHPUnit\Framework\TestCase;

final class MyMiddlewareTest extends TestCase
{
    public function testItStampsThemeCookie(): void
    {
        $response = (new MyMiddleware(new CookieManager()))->process($request, $handler);

        self::assertEquals(
            new SetCookie('theme', 'dark'),
            (new CookieManager())->getFromResponse($response, 'theme'),
        );
    }
}
```

Build PSR-7 fixtures with `laminas/diactoros` (already a dev dependency of the framework). For unit tests that do not need a full request object, stubs work — `tests/Cookie/CookieManagerTest.php` uses `createStub(RequestInterface::class)` and mocks only `getHeaderLine` and `getHeader`.

## Extending

There are no extension points by design — the cookie format is fixed by the spec, and the value objects are `final` to keep the readonly contract enforceable. If you need behaviour beyond `Cookie` / `SetCookie` (e.g. typed values like integers or arrays), wrap them in your own value object rather than subclassing.

The one place you can plug in is the collection layer. `CookieCollection` and `SetCookieCollection` both extend `Altair\Structure\Map`, so you can pass them anywhere a Map is expected and use the full Map API to filter, sort, or transform.

## Recipes

### A long-lived "remember me" cookie

`SetCookieFactory::createRemembered()` stamps a `+5 years` expiry, which is the conventional "remember me" ceiling.

```php
use Altair\Cookie\Factory\SetCookieFactory;

$response = $manager->setOnResponse(
    $response,
    SetCookieFactory::createRemembered('remember_me', $token)
        ->withPath('/')
        ->withSecure(true)
        ->withHttpOnly(true),
);
```

### Expiring a cookie on logout

Pair `expireOnResponse()` with whatever signals end-of-session. The expiry is set to `-5 years`, which all browsers treat as "delete immediately".

```php
$response = $manager->expireOnResponse($response, 'remember_me');
$response = $manager->expireOnResponse($response, 'session_id');
```

### Forwarding cookies through a proxy

When you act as a proxy, you want to copy the request's `Cookie` header onto an outbound request and the upstream response's `Set-Cookie` headers onto your own response, preserving every attribute.

```php
use Altair\Cookie\Factory\CookieFactory;
use Altair\Cookie\Factory\SetCookieFactory;

$outbound = CookieFactory::createCollectionFromRequest($incomingRequest)
    ->injectIntoRequestHeader($outboundRequest);

$upstreamResponse = $client->sendRequest($outbound);

$downstream = SetCookieFactory::createCollectionFromResponse($upstreamResponse);
// re-inject without modification, or filter/rewrite first
$response = $downstream->injectIntoResponseHeader($response);
```

The collections give you a vantage point between read and write — perfect for stripping `Domain` attributes, downgrading `Secure` in dev, or filtering by name prefix.

### Setting a session cookie that mirrors PHP's `session_*` config

This is what [http.md](./http.md)'s `SessionHeadersMiddleware` does internally. The shape is portable to your own middleware.

```php
$params = session_get_cookie_params();

$response = $manager->setOnResponse(
    $response,
    (new SetCookie((string) session_name(), $sessionId))
        ->withDomain($params['domain'] ?? null)
        ->withPath($params['path'] ?? null)
        ->withSecure($params['secure'] ?? false)
        ->withHttpOnly($params['httponly'] ?? false),
);
```

## Related packages

- [structure.md](./structure.md) — `Map`, the base class behind `CookieCollection` and `SetCookieCollection`. Read this if you want to know what methods the collections inherit.
- [http.md](./http.md) — the PSR-15 middleware pipeline that calls into `CookieManager` (notably `SessionHeadersMiddleware`).
- [session.md](./session.md) — server-side session storage. The session ID itself is delivered to the browser as a cookie produced through this package, but the storage of session data lives there.
- [security.md](./security.md) — when you need to sign or encrypt a cookie value before it leaves the server.

## Limitations

- The package speaks the cookie wire format but takes no opinion on `SameSite`. If you need `SameSite=Lax|Strict|None`, attach it via `withDomain()`-adjacent attributes on the response yourself, or set it at the web-server level. A future revision may add `SameSite` as a first-class attribute on `SetCookie`.
- `SetCookieCollection::sum()` throws `InvalidCallException` — the inherited `Map::sum()` does not make sense for cookie values, so it is explicitly disabled.
- Cookie values are url-encoded on serialization. If you need raw bytes through, encode them yourself (e.g. base64) before constructing the `SetCookie`.
