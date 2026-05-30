---
title: List and read examples programmatically (not via CLI)
scenario: Build a custom agent surface, doc generator, or CI gate that walks the example library and reasons about each entry.
packages: [examples]
since: 2.0.0
tested_by: tests/Examples/ExamplesLibraryProgrammaticAccessTest.php
---

# List and read examples programmatically

The CLI (`bin/altair examples:list`) and MCP tools (`framework__list_examples`) both wrap the same `ExampleRepository`. Anything they can do, your code can do directly — useful for custom agent surfaces, doc generators, or "examples must reference an existing test" gates.

```php
use Altair\Examples\Library\ExampleParser;
use Altair\Examples\Library\ExampleRepository;
use Altair\Examples\Library\IndexBuilder;

// Walk the project's library at .altair/examples/
$repository = new ExampleRepository('/path/to/project/.altair/examples', new ExampleParser());

// Every example, sorted by id
foreach ($repository->findAll() as $example) {
    // $example->id, ->title, ->packages, ->testedBy, ->body
}

// Filter by package
$repository->findByPackage('http');

// Read one specific example by id
$repository->findById('http/basic-endpoint')->body;

// Free-text substring search across id + title + scenario + body
$repository->search('outbox');

// Rebuild the deterministic index.json
(new IndexBuilder($repository))->writeTo('/path/to/project/.altair/examples/index.json');
```

## Gotchas

- **The repository walks the filesystem on first call and caches the result for its lifetime.** Construct a new one when content changes — or use `bin/altair examples:index` to refresh the on-disk index.
- **`findById` throws `ExampleNotFoundException`**; catch it where you can react meaningfully (the MCP `read_example` tool re-throws as `McpException` for the agent).
- **`IndexBuilder::build()` is a pure function**: same repository contents → same JSON string. Wire it into a CI step with `bin/altair examples:index --check` to gate against drift between content and the published index.
- **Skip `index.json` — it is generated, not authored.** The parser would reject it (no frontmatter) if it lived under the library root, but the repository's filename filter (`.md` extension) already excludes it.
