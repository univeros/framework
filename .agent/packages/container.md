# univeros/container  ·  Altair\Container

**Purpose:** The Altair Container package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `BuilderInterface` | _(marker)_ |  |  |
| `ExecutableBuilderInterface` | `isExecutable(mixed)` | `bool` | extends `BuilderInterface` |
| `ReflectionCacheInterface` | `get(string)` | `mixed` | constants: `CLASSES_KEY_PREFIX`, `CONSTRUCTORS_KEY_PREFIX`, `CONSTRUCTOR_PARAMETERS_KEY_PREFIX`, `FUNCTIONS_KEY_PREFIX`, `FUNCTION_PARAMETERS_KEY_PREFIX`, `METHODS_KEY_PREFIX` |
|  | `put(string, mixed)` | `ReflectionCacheInterface` |  |
| `ReflectionInterface` | `getClass(string)` | `ReflectionClass` |  |
|  | `getConstructor(string)` | `ReflectionMethod\|null` |  |
|  | `getConstructorParameters(string)` | `array\|null` |  |
|  | `getFunction(mixed)` | `ReflectionFunction` |  |
|  | `getMethod(mixed, string)` | `ReflectionMethod` |  |
|  | `getParameterTypeHint(ReflectionFunctionAbstract, ReflectionParameter)` | `string\|null` |  |

## Concrete classes

- `AliasesCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `ArgumentsBuilder` — implements `BuilderInterface`
- `ArrayCache` — implements `ReflectionCacheInterface`
- `CachedReflection` — implements `ReflectionInterface`
- `ClassDefinitionsCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `Container` — implements `ContainerInterface`
- `Definition`
- `DelegatesCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `Executable`
- `ExecutableBuilder` — implements `BuilderInterface`, `ExecutableBuilderInterface`
- `FileCache` — implements `ReflectionCacheInterface`
- `ParameterDefinitionsCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `PreparesCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `SharesCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `StandardReflection` — implements `ReflectionInterface`

## Tests as documentation

- `tests/Container/ContainerTest.php`

## Related packages

- `psr/container`
- `univeros/structure`
