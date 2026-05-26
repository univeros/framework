# CLAUDE.md

Claude Code entry point for the **Univeros / Altair Framework** (`univeros/framework`, PHP 8.3+).

> **The canonical project guide is [AGENT.md](AGENT.md).** Read it first.
> This file adds only Claude-Code-specific guidance on top.

---

## 1. Quick orientation

- **What it is:** A monorepo PHP framework with 17 sub-packages under `src/Altair/*`, bundled via composer `replace`.
- **Where conventions live:** [AGENT.md](AGENT.md) §5 (coding style), §6 (testing).
- **Where the modernization roadmap lives:** [AGENT.md](AGENT.md) §7. Phase 1 done; Phases 2-4 pending.

If you're being asked to make a change, read the relevant sub-package's `Contracts/` directory before its concrete classes.

---

## 2. Commands you can run

```bash
composer install
composer update -W

composer qa            # cs + stan + test — the pre-commit gate
composer test          # PHPUnit 11
composer cs            # PHP-CS-Fixer dry-run
composer cs:fix        # apply
composer stan          # PHPStan
composer rector        # Rector dry-run (PHP 8.3 + code-quality sets)
composer rector:fix    # apply Rector
```

CI mirrors these: `.github/workflows/ci.yml`.

**Note:** PHP may not be installed on the user's local machine. If `composer`/`php` aren't on PATH, **say so explicitly** rather than claiming success. Don't fabricate test output.

---

## 3. Claude-Code-specific behavior

### Agents to use proactively

| Situation | Subagent |
|---|---|
| Bulk search across the 388 source files | `Explore` |
| Designing a new sub-package or refactor spanning several files | `architect` then `planner` |
| Writing new code or fixing bugs | `tdd-guide` (write the failing test first) |
| Immediately after code changes | `code-reviewer` |
| Before any commit touching auth/security/crypto/input parsing | `security-reviewer` |
| Build/composer/type errors | `build-error-resolver` |
| Removing unused code | `refactor-cleaner` |
| Updating this file or AGENT.md after structural changes | `doc-updater` |

### Skills to use

- `/code-review` after writing code (security + quality + style)
- `/security-review` before committing anything touching `Altair\Security\*`, `Altair\Session\*`, `Altair\Http\Middleware\Csrf*`, `Altair\Cookie\*`, or JWT handling
- `/verify` to run the full QA pipeline (cs + stan + test)
- `/refactor-clean` for dead-code passes (esp. after Phase 2 Rector run)
- `/tdd` for new features — tests first, implementation second

### Generating HTTP endpoints

The `univeros/scaffold` sub-package emits Action / Input / Responder / domain stub / PHPUnit test / OpenAPI fragment / route entry from a single YAML spec.

```bash
bin/altair spec:scaffold api/users/create.yaml          # write files
bin/altair spec:scaffold api/users/create.yaml --dry-run
bin/altair spec:scaffold api/ --force                   # batch + overwrite
bin/altair spec:emit-openapi --out docs/openapi.yaml    # merge fragments
bin/altair spec:lint                                    # drift check
```

When you add a new HTTP endpoint, write the YAML spec first and scaffold it — don't hand-write the Action/Input/Responder triple. After hand-editing generated files, run `bin/altair spec:lint` so drift surfaces in CI.

### Plan/Skill choices for the open work

- **Phase 2 (Rector):** Don't `Plan` it — just run `composer rector:fix`, then `composer cs:fix && composer test`. Triage failures one at a time.
- **Phase 3 (manual breaking changes):** Use `planner` first. Each item in [AGENT.md §7](AGENT.md#phase-3--pending-manual-breaking-change-migrations) is independently scoped — do one at a time, run tests between.
- **Phase 4 (PHPStan + PHPUnit attributes):** Raise PHPStan level one notch at a time. Use `phpstan.neon.dist`'s `ignoreErrors` only with a comment explaining why.

---

## 4. Conventions Claude must follow

These are stricter than what other agents need because of past patterns in this codebase:

- **Immutability — required.** Never mutate value objects. New copies via `withFoo()` methods. See `Altair\Cookie\Cookie` as the reference implementation.
- **Many small files > few big ones.** 200-400 LOC typical, 800 hard cap. Extract aggressively.
- **`declare(strict_types=1)` is non-negotiable** — every file, every time. Currently 388/388 source files comply; don't be the one who breaks it.
- **Native types beat PHPDoc.** Add PHPDoc only for `array<K, V>` shapes or unions PHP can't express natively. Don't write `@param string $foo` next to `string $foo` — Rector deletes those anyway.
- **No emojis** in source files, commit messages, or docs unless the user explicitly asks for them.
- **No new code without tests.** TDD per [AGENT.md §6](AGENT.md#6-testing). 80%+ coverage on new code.
- **Don't reintroduce abandoned deps** (Zend\Diactoros, relay/middleware, Flysystem v1 adapters — see [AGENT.md §5](AGENT.md#what-not-to-do)).

---

## 5. Things to flag to the user

When you encounter these, **stop and surface them** instead of working around:

1. **Composer install fails:** likely a version conflict from Phase 1. Don't relax constraints — diagnose with `composer why-not <pkg> <version>` and report.
2. **A file still uses `Zend\Diactoros\*`:** Phase 1 swap missed it. Fix the import and report which file.
3. **A test uses `Relay\RelayBuilder` or `$next($req, $res)`:** double-pass middleware leak. Belongs to Phase 3a — flag for batched work, don't fix in isolation unless asked.
4. **PHPStan finds an issue you'd ignore:** add to `ignoreErrors` only with an inline `// reason: …` comment.
5. **A change requires editing 10+ files mechanically:** stop, suggest Rector or a one-off transform script instead.

---

## 6. Git / PR workflow

- **Branch:** work on `master` directly for this project (no PR workflow established yet — confirm with user before opening one).
- **Commits:** style is `re #<issue> <subject>` (see `git log`). Keep that style for issue-linked work; conventional commits (`feat:`, `fix:`) for ad-hoc work.
- **Never auto-push.** Always confirm before `git push`.
- **Never `--no-verify`.** If pre-commit (CS-Fixer) fails, fix the underlying lint issue.

---

## 7. State at last update (2026-05)

- Working tree may contain uncommitted Phase 1 changes. Run `git status` first.
- `composer.lock` is gitignored and will be regenerated by the user.
- Open items: see [AGENT.md §7](AGENT.md#7-modernization-status-started-2026-05) Phases 2-4.

When in doubt, **read [AGENT.md](AGENT.md) first**, then ask the user before making architectural choices.
