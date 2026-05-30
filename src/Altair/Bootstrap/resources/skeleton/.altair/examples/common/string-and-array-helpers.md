---
title: Use Str and Arr helpers for tidy string and array work
scenario: You need string truncation or array column extraction without rolling helpers each time.
packages: [common]
since: 2.0.0
tested_by: tests/Examples/CommonStringAndArrayHelpersTest.php
---

# Use Str and Arr helpers for tidy string and array work

`Altair\Common\Support\Str` and `Altair\Common\Support\Arr` are pure utility classes — the grab-bag of helpers that show up in every codebase, but consistent across the framework so two packages never disagree about (e.g.) what "truncate with ellipsis" means.

```php
use Altair\Common\Support\Arr;
use Altair\Common\Support\Str;

// Truncate a long string with an ellipsis suffix.
$str = new Str();
$str->truncate('The quick brown fox jumps over the lazy dog', 16);
// => 'The quick brown...'

// Pull a single column out of an array of records.
$users = [
    ['id' => 1, 'email' => 'alice@example.com'],
    ['id' => 2, 'email' => 'bob@example.com'],
    ['id' => 3, 'email' => 'eve@example.com'],
];

Arr::getColumn($users, 'email');
// => ['alice@example.com', 'bob@example.com', 'eve@example.com']
```

## Gotchas

- **`Str` is instance-methods, `Arr` is static methods.** That is a historical split that's now baked in — don't refactor it casually, there's downstream code keying on the shape.
- **`truncate()`'s third argument is the suffix, not a flag.** The default `'...'` is three characters; if you want a true ellipsis character pass `"\u{2026}"`.
- **`Arr::getColumn` keeps keys by default** — pass `false` as the third argument when you want a renumbered list. The framework's convention is to keep keys; the third arg exists for legacy callers.
