# univeros/container  ·  Altair\Container

**Purpose:** Reflection-backed PSR-11 dependency-injection container with auto-wiring, contextual bindings, tagged services, decorators, and child scopes.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `DefinitionInterface` | `concrete()` | `string\|null` |  |
|  | `factory()` | `Closure\|null` |  |
|  | `hasInstance()` | `bool` |  |
|  | `hasValue()` | `bool` |  |
|  | `id()` | `string` |  |
|  | `instance()` | `object\|null` |  |
|  | `isLazy()` | `bool` |  |
|  | `isShared()` | `bool` |  |
|  | `parameters()` | `array` |  |
|  | `tags()` | `array` |  |
|  | `value()` | `mixed` |  |
| `FactoryInterface` | `make(string, array)` | `object` |  |
| `InvokerInterface` | `call(callable\|array\|string, array)` | `mixed` |  |
| `ReflectionCacheInterface` | `get(string)` | `ClassMetadata\|null` |  |
|  | `put(string, ClassMetadata)` | `void` |  |
| `ReflectorInterface` | `classMetadata(string)` | `ClassMetadata` |  |
|  | `parametersOf(ReflectionFunctionAbstract)` | `array` |  |

## Concrete classes

- `ArrayReflectionCache` _(final)_ — implements `ReflectionCacheInterface`
- `Autowire` _(final)_
- `CachedReflector` _(final)_ — implements `ReflectorInterface`
- `ClassMetadata` _(final)_
- `Container` _(final)_ — implements `ContainerInterface`, `FactoryInterface`, `InvokerInterface`
- `ContextualBindingBuilder` _(final)_
- `Definition` _(final)_ — implements `DefinitionInterface`
- `Factory` _(final)_
- `FileReflectionCache` _(final)_ — implements `ReflectionCacheInterface`
- `Inject` _(final)_
- `Invoker` _(final)_
- `Lazy` _(final)_
- `LazyFactory` _(final)_
- `NameNormalizer` _(final)_
- `ParameterMetadata` _(final)_
- `ParameterResolver` _(final)_
- `Reflector` _(final)_ — implements `ReflectorInterface`
- `ResolutionStack` _(final)_
- `Resolver` _(final)_
- `Tag` _(final)_

## Tests as documentation

- `tests/Container/ContainerTest.php`

## Related packages

- `psr/container`
- `univeros/structure`
