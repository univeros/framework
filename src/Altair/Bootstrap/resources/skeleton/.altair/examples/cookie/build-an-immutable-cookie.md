---
title: Build an immutable cookie value object
scenario: Construct a Cookie, derive a new value from it without mutating the original — the framework's readonly-everywhere posture in miniature.
packages: [cookie]
since: 2.0.0
tested_by: tests/Examples/CookieBuildAnImmutableCookieTest.php
---

# Build an immutable cookie value object

`Altair\Cookie\Cookie` is a `final readonly` value object. You can never *mutate* a cookie — only derive a new one via `withValue()`. The `__toString()` returns the wire form (`name=urlencoded-value`), so passing it through any string context (PSR-7 header, `echo`, JSON) just works.

```php
use Altair\Cookie\Cookie;

$session = new Cookie('session', 'abc123');

(string) $session;             // 'session=abc123'

// Derive a new cookie — the original is untouched.
$rotated = $session->withValue('def456');

(string) $session;             // 'session=abc123' (still!)
(string) $rotated;             // 'session=def456'
```

## Gotchas

- **The value is URL-encoded on stringify**, so a value of `a b` renders as `a%20b`. If the consumer is going to re-decode it, that round-trip is symmetric — no manual escaping needed.
- **A null value renders as an empty value** (`name=`). That is the wire form for "clear this cookie"; combine with a `Set-Cookie` `Expires` in the past to actually delete it from a browser.
- **`Cookie` is the read shape; `SetCookie` carries attributes (path, domain, expires, secure, httponly, samesite).** Use `Altair\Cookie\SetCookie` when emitting a response.
