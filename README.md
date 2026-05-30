### Important!
The framework is currently under heavy development. The initial reason of its creation was to gain knowledge and understanding of the modern coding proposals of PHP. I strongly believed that the best way to do that was to create my very own framework. I would like to personally thank all those amazing developers whose code is a true inspiration for me. 

***Univeros has no intention to compete with other frameworks.***

I have read|studied|transform|modify|used code from what I believed were amazing implementations and also created my very own for those I envision should work differently. 

This framework contains the tools of an application architecture named "univeros/univeros". It will have all the **backend** required tools for the architecture to work. 

Nothing is yet published on composer, API is being defined as it goes and if you use any of its components, expect bugs until its total completion on those not yet tested. Until then, feel free to use the code within this repository. do whatever you wish with it and let me know where you use it, would be nice to see how you envisioned what was developed. 

## Univeros

## Repositories

Univeros is split across three top-level repositories — `univeros/univeros`, `univeros/framework`, `univeros/docs` — plus a read-only mirror per sub-package.

- **[univeros/univeros](https://github.com/univeros/univeros)** — the create-project starter. `composer create-project univeros/univeros myapp` lays down a runnable Altair API.
- **[univeros/framework](https://github.com/univeros/framework)** — this repo. The library you depend on (`composer require univeros/framework`).
- **[univeros/docs](https://github.com/univeros/docs)** — read-only mirror of the [docs/](docs/) tree from this monorepo. Open issues and PRs against `univeros/framework`; the docs repo is the published surface only.

## Sub-packages

The framework is composed of 35 standalone PHP packages under [src/Altair/](src/Altair/). Each is published as a read-only repository at `github.com/univeros/<name>`. Pull the whole framework via:

```bash
composer require univeros/framework
```

…or compose individual packages. A few representative ones:

```bash
composer require univeros/http          # PSR-7 + PSR-15 stack, single-pass middleware
composer require univeros/scaffold      # YAML spec → Action/Input/Responder + OpenAPI + tests
composer require univeros/persistence   # Repository/UnitOfWork bridge over Cycle ORM v2
composer require univeros/messaging     # MessageBus bridge over Symfony Messenger
composer require univeros/events        # Append-only mutation event log for agents
```

Per-package documentation lives under [docs/packages/](docs/packages/). The complete published list:

`agent-spec`, `bootstrap`, `cache`, `cli`, `common`, `configuration`, `container`, `cookie`, `courier`, `data`, `doctor`, `eval`, `events`, `filesystem`, `happen`, `http`, `index`, `introspection`, `mcp`, `messaging`, `middleware`, `migration-intelligence`, `observability`, `observatory`, `persistence`, `profiling`, `sanitation`, `scaffold`, `security`, `session`, `structure`, `suggest`, `test-reporter`, `tinker`, `validation`.

Splits are produced automatically by [.github/workflows/split.yml](.github/workflows/split.yml) — see [docs/packages/split-publish.md](docs/packages/split-publish.md) for the operator runbook. All changes belong in this monorepo; every split repo (including `univeros/univeros` and `univeros/docs`) is a read-only mirror.

## Learning Univeros

## Contributing

## Security Vulnerabilities

## License

The Univeros framework is open [MIT license](http://opensource.org/licenses/MIT).
