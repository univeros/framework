# Split publishing

> Operator runbook for publishing every `src/Altair/*` sub-package as a standalone read-only repository at `github.com/univeros/<name>` and as `univeros/<name>` on Packagist. Driven by [`.github/workflows/split.yml`](../../.github/workflows/split.yml).

**Source of truth:** `src/Altair/<Package>/` in the [`univeros/framework`](https://github.com/univeros/framework) monorepo. Every directory under `src/Altair/` that ships a `composer.json` is automatically a publishable package; the workflow's drift guard fails if the matrix in `split.yml` falls out of sync with the filesystem.

## How the workflow works

`splitsh/lite` rewrites the monorepo's history for a single subtree (`src/Altair/<Package>/`) into a synthetic commit graph whose root is that subtree. The resulting SHA is force-pushed to `master` (or the tag ref, on tag pushes) of `github.com/univeros/<name>`. Each sub-repo therefore looks like it had always lived at the top level of its own repository — `composer require univeros/cache` pulls the package alone, not the whole framework.

The split is reproducible: re-running it on the same commit produces the same SHA. The split SHAs are not the same as the monorepo's commit SHAs.

## Initial setup (one-time)

Before the workflow can push anything, the following must exist:

1. **GitHub repositories** for each of the 35 packages. The original 16 (`cache`, `common`, `configuration`, `container`, `cookie`, `courier`, `data`, `filesystem`, `happen`, `http`, `middleware`, `sanitation`, `security`, `session`, `structure`, `validation`) already exist. The other 19 must be created:

   ```bash
   for pkg in agent-spec bootstrap cli doctor eval events index introspection \
              mcp messaging migration-intelligence observability observatory \
              persistence profiling scaffold suggest test-reporter tinker; do
     gh repo create "univeros/$pkg" \
       --public \
       --description "[READ ONLY] Subtree split of the Univeros $pkg component" \
       --homepage "https://univeros.io"
   done
   ```

   Each new repo can be empty — the first workflow run will force-push the split contents.

2. **Delete stale repositories** that no longer correspond to a `src/Altair/*` directory:

   ```bash
   gh repo delete univeros/queue --yes   # replaced by univeros/messaging in 2026-05
   ```

3. **Authentication token.** Generate a fine-grained personal access token with `Contents: write` on all 35 sub-repos (or a classic PAT with the `repo` scope). Store it on `univeros/framework` as the `SPLIT_TOKEN` repository secret:

   ```bash
   gh secret set SPLIT_TOKEN --repo univeros/framework --body "<the-token>"
   ```

   Deploy keys (one keypair per sub-repo) are the alternative if PAT rotation is a concern; the workflow would need to be adapted to use SSH URLs in that case.

4. **Default branch on each sub-repo** must be `master` (matching the monorepo). New `gh repo create` defaults to `main` — fix it:

   ```bash
   for pkg in $(gh repo list univeros --json name -q '.[].name' | grep -v '^framework$\|^univeros$'); do
     gh api -X PATCH "repos/univeros/$pkg" -f default_branch=master 2>/dev/null || true
   done
   ```

## Running the workflow

Triggered manually via `workflow_dispatch` — the comment at the top of `split.yml` explains the rationale (the framework is not yet 1.0; auto-on-push will be enabled once it is).

```bash
# Dry-run: compute splits for every package without pushing
gh workflow run split.yml -f dry_run=true

# Real run: split + push all 35 packages
gh workflow run split.yml

# Single package (matches the workflow_dispatch dropdown)
gh workflow run split.yml -f package=scaffold
```

Tail it:

```bash
gh run watch
```

## First release: tagging `v2.0.0`

Tags propagate the same way branches do — push `v2.0.0` to the monorepo, the workflow re-splits each package at the tagged commit and pushes the same tag to each sub-repo.

```bash
git tag -a v2.0.0 -m "Univeros 2.0.0 — PHP 8.3 modernization"
git push origin v2.0.0
gh workflow run split.yml   # triggers split + tag propagation
```

Verify the tag landed on a sample sub-repo:

```bash
gh release list --repo univeros/cache
gh api repos/univeros/cache/git/refs/tags/v2.0.0
```

## Packagist registration

Each sub-package must be submitted to packagist.org once. After that, Packagist subscribes to the GitHub webhook and picks up future tags automatically.

Submit in dependency order so each upload finds its declared deps already present on Packagist:

1. Leaf packages (no inter-deps): `common`, `structure`
2. `container` (depends on `structure`)
3. `configuration` (depends on `container`)
4. `middleware`, `security` (no `univeros/*` deps)
5. Middle layer: `cache`, `cookie`, `data`, `events`, `happen`, `session`
6. Top of the original stack: `filesystem`, `sanitation`, `validation`, `courier`, `http`
7. The 2026 additions, in alphabetical order — none of them are required by the original 16, so the order inside this batch does not matter: `agent-spec`, `bootstrap`, `cli`, `doctor`, `eval`, `index`, `introspection`, `mcp`, `messaging`, `migration-intelligence`, `observability`, `observatory`, `persistence`, `profiling`, `scaffold`, `suggest`, `test-reporter`, `tinker`

## Adding a new sub-package

1. Create the package under `src/Altair/<Name>/` with its own `composer.json` declaring `"name": "univeros/<name>"`.
2. Add the matrix entry to `.github/workflows/split.yml` in the `full=` JSON block. The drift guard will refuse to run until this is done.
3. Add the package name to the `workflow_dispatch.inputs.package.options` list so the dropdown stays in sync.
4. Create the GitHub repository (`gh repo create univeros/<name> --public ...`) and grant `SPLIT_TOKEN` write access to it.
5. Submit to Packagist after the next tagged release.

## Removing a sub-package

1. Delete `src/Altair/<Name>/` (the matrix drift guard will block the workflow until you also drop its entry).
2. Remove the matrix entry from `split.yml` and from the dropdown options.
3. Archive (don't delete) the GitHub repository so existing `composer.lock` files keep resolving:

   ```bash
   gh api -X PATCH repos/univeros/<name> -f archived=true
   ```

4. Mark the package as abandoned on Packagist, pointing to its replacement if any.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `splitsh-lite produced an empty SHA` | The path in the matrix doesn't exist, or the subtree is empty at the chosen ref. |
| `403 from github.com` when pushing | `SPLIT_TOKEN` doesn't have write access to that sub-repo, or it shadowed the GitHub Actions token (the workflow already disables `persist-credentials` for the main checkout and clears `extraheader` on push — don't re-enable those). |
| `split.yml matrix is out of sync with src/Altair/*` | A package directory exists with a `composer.json` but is missing from the matrix, or the matrix references a path that no longer exists. The error message includes a diff. |
| Tag propagated to some sub-repos but not others | `fail-fast: false` is set, so individual sub-repos can fail independently. Check the matrix job logs and re-run the failed jobs once the cause is fixed. |
| Packagist shows an old version after a fresh tag | The GitHub webhook may not be wired up. Trigger a manual update at `https://packagist.org/packages/univeros/<name>` → "Update". |
