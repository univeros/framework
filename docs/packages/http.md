# Http

PSR-15 HTTP foundation with the framework's signature Action/Domain/Input/Responder request lifecycle, FastRoute-based routing, content negotiation, and JWT authentication.

**Package:** `univeros/http`
**Namespace:** `Altair\Http`
**PSR compliance:** PSR-7 (`psr/http-message ^2`), PSR-15 (`psr/http-server-middleware ^1`, `psr/http-server-handler ^1`), PSR-17 (`psr/http-factory ^1.1`)

---

## Introduction

The Http package is the outermost layer of the Altair framework. Every HTTP request your application receives enters through a PSR-15 middleware pipeline and exits as a PSR-7 response — without a single line of framework logic leaking into your domain classes.

The pipeline runs in single-pass mode using `relay/relay ^2`. Each middleware receives a `ServerRequestInterface` and a `RequestHandlerInterface`, does its work, calls `$handler->handle($request)` to continue the chain, and returns a `ResponseInterface`. There is no second `$response` parameter passed down the chain, and there is no `RelayBuilder` or `$next($req, $res)` double-pass signature. The dispatcher is `Relay\Relay`, wired with a `ContainerResolver` that lazily builds middleware from class names via the DI container.

The framework's most opinionated contribution is the **Action / Domain / Input / Responder** (ADIR) request lifecycle. Rather than routing directly to a controller that mixes HTTP concerns with business logic, the package separates a request into four collaborating objects. An `Input` extracts a domain-neutral data collection from the request. A `Domain` acts on that collection and returns a `Payload`. An `Action` binds those three together and names the fourth collaborator, the `Responder`, which turns the payload into an HTTP response. The `ActionMiddleware` orchestrates the four objects; the `DispatcherMiddleware` uses FastRoute to resolve which `Action` to invoke.

Routing is powered by `nikic/fast-route ^1.3`. Routes are declared as a `RouteCollection` (a `Map` keyed by `"METHOD /path"`) and registered with the FastRoute dispatcher via `FastRouteConfiguration`. Route segment variables (e.g. `/users/{id:\d+}`) are automatically set as request attributes by `DispatcherMiddleware` and are therefore visible to any `Input` that reads `$request->getAttributes()`.

Authentication ships in three flavours. `BasicAuthenticationMiddleware` and `DigestAuthenticationMiddleware` handle the corresponding HTTP authentication schemes. `TokenAuthenticationMiddleware` covers token-based flows — including JWT — via composable `TokenExtractorInterface`, `CredentialsExtractorInterface`, and `TokenFactoryInterface` collaborators. The JWT implementation wraps Lcobucci JWT and is configured through `LcobucciTokenConfiguration` and related environment variables. All three middleware share the `HttpAuthenticationAwareTrait`, which provides rule-based request filtering (path and method passthrough rules) and an HTTPS-only enforcement gate.

The package does not handle HTTP/2 push, server-sent events, WebSockets, long-polling, or any stateful connection model. It does not include an HTML template engine beyond the optional `PhpViewFormatter`. For rate limiting it ships `RateLimitMiddleware` — a fixed-window PSR-15 limiter backed by any PSR-16 cache pool, with a pluggable key resolver (IP by default; API-key / user-id is one line). It complements rather than replaces edge / reverse-proxy rate limiting — the proxy stops floods at the door; this catches the surviving abuse with per-key precision.

---

## Installation

Install the package with Composer:

```bash
composer require univeros/http
```

The package requires **PHP 8.3 or later** and the following runtime extensions:

- **`ext-json`** — required; used by `JsonContentMiddleware`, `JsonFormatter`, and `InputParser`.
- **`ext-gd`** — optional; required only if you use `DefaultErrorHandler`'s image error renderers (JPEG, GIF, PNG responses for media requests).

`laminas/laminas-diactoros ^3.5` is pulled in automatically as the PSR-7 implementation. If you use the full `univeros/framework` meta-package this package is already included.

---

## Quick start

This example bootstraps a minimal application: a PSR-15 pipeline, one route, and one Action that returns JSON. It assumes an `Altair\Container\Container` instance is already configured.

```php
use Altair\Http\Base\Action;
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Collection\RouteCollection;
use Altair\Http\Configuration\FastRouteConfiguration;
use Altair\Http\Configuration\HttpMessageConfiguration;
use Altair\Http\Configuration\PayloadConfiguration;
use Altair\Http\Configuration\RelayConfiguration;
use Altair\Http\Middleware\ActionMiddleware;
use Altair\Http\Middleware\DispatcherMiddleware;
use Altair\Http\Resolver\ContainerResolver;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Relay\Relay;

// 1. Register configuration bindings in the container.
(new HttpMessageConfiguration())->apply($container);
(new PayloadConfiguration())->apply($container);

// 2. Define routes. The key is "METHOD /path"; the value is an Action instance.
$routes = new RouteCollection();
$routes->put('GET /hello/{name}', new Action(HelloDomain::class));

(new FastRouteConfiguration($routes))->apply($container);

// 3. Build the middleware queue.
$queue = new MiddlewareCollection();
$queue->push(DispatcherMiddleware::class);
$queue->push(ActionMiddleware::class);

// 4. Wire Relay with the ContainerResolver.
$resolver  = $container->make(ContainerResolver::class);
$relay     = new Relay($queue->toArray(), $resolver);

// 5. Dispatch the incoming request.
$request  = ServerRequestFactory::fromGlobals();
$response = $relay->handle($request);

// 6. Emit the response (use a PSR-7 emitter of your choice).
(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
```

`HelloDomain` must implement `DomainInterface`. `ActionMiddleware` resolves the `Input` and `Responder` class names from the `Action` object; because you did not pass them, the defaults (`InputParser` and `CompoundResponder`) apply.

---

## Concepts

### The Action / Domain / Input / Responder pattern

The ADIR pattern separates an HTTP request into four responsibilities, each with one job.

**Input** (`Altair\Http\Contracts\InputInterface`) reads the PSR-7 request and returns an `InputCollection` — a plain `Map` of domain-neutral data. An Input knows about HTTP: query strings, parsed bodies, uploaded files, route attributes, and cookies. It does not call the database or apply business rules.

```php
public function __invoke(ServerRequestInterface $request): InputCollection;
```

**Domain** (`Altair\Http\Contracts\DomainInterface`) receives an `InputCollection` and returns a `PayloadInterface`. A Domain knows nothing about HTTP. It enforces business rules, reads from repositories, and reports its outcome via the payload's status, output, and messages fields.

```php
public function __invoke(InputCollection $input): PayloadInterface;
```

**Action** (`Altair\Http\Base\Action`) is a value object — not a controller. It holds three class names: the Domain, the Input, and the Responder. `ActionMiddleware` reads the `Action` from the request attribute set by `DispatcherMiddleware`, then orchestrates `input($request) -> domain(collection) -> responder(request, response, payload)`.

**Responder** (`Altair\Http\Contracts\ResponderInterface`) turns a `PayloadInterface` into a `ResponseInterface`. A Responder knows about HTTP status codes, content-type headers, and response bodies. It does not call the domain.

```php
public function __invoke(
    ServerRequestInterface $request,
    ResponseInterface $response,
    PayloadInterface $payload,
): ResponseInterface;
```

The `ActionMiddleware` executes the full cycle as one expression:

```php
return $responder($request, $this->responseFactory->createResponse(), $domain($input($request)));
```

### The Payload

`Altair\Http\Base\Payload` is an immutable value object that carries the domain's answer back to the HTTP layer. It has four orthogonal parts:

- **status** — a domain-level status integer (mapped to an HTTP status code by `StatusResponder`).
- **output** — the data array to serialize into the response body.
- **messages** — validation errors, notices, or other human-readable strings.
- **settings** — arbitrary key-value pairs used by responders and formatters (e.g. `redirect`, `template`, `layout`).

All mutating methods return new copies (`withStatus()`, `withOutput()`, `withMessages()`, `withSetting()`, `withoutSetting()`). The domain never modifies a payload in place.

### The PSR-15 pipeline

`MiddlewareCollection` is a `Queue` of middleware class names (or objects). `RelayConfiguration` wires `Relay\Relay` to consume it via a `ContainerResolver`, so class names are lazily instantiated from the container the first time they are needed.

Middleware ordering matters. A middleware that decorates the response (CORS headers, HTTP cache validation, content-type) must appear **before** `ActionMiddleware` in the queue so that it wraps the response that bubbles back up. A middleware that guards the request (authentication, CSRF, spam blocker) must also appear before `ActionMiddleware` so it can short-circuit before the domain is called.

A typical production queue looks like this:

```
ExceptionHandlerMiddleware   ← outermost; catches everything
IpAddressMiddleware          ← attaches IP list to request attributes
SpamBlockerMiddleware        ← 403 on known referrer spam
CorsMiddleware               ← pre-flight and CORS headers
SessionHeadersMiddleware     ← session cookie management
CsrfMiddleware               ← CSRF validation + token injection
TokenAuthenticationMiddleware← JWT / credential auth
FormatNegotiatorMiddleware   ← resolves Accept header → format attribute
JsonContentMiddleware        ← parses application/json request bodies
FormContentMiddleware        ← parses application/x-www-form-urlencoded bodies
CacheMiddleware              ← HTTP cache (ETag, Last-Modified, 304)
DispatcherMiddleware         ← FastRoute → Action on request attribute
ActionMiddleware             ← executes Input → Domain → Responder
```

### Request attributes

Middleware communicates with downstream middleware and Actions via PSR-7 request attributes. `MiddlewareInterface` defines the standard attribute keys as typed class constants:

| Constant | Value | Set by |
|---|---|---|
| `ATTRIBUTE_IP_ADDRESS` | `altair:http:ip-address` | `IpAddressMiddleware` |
| `ATTRIBUTE_ACTION` | `altair:http:action` | `DispatcherMiddleware` |
| `ATTRIBUTE_FORMAT` | `altair:http:format` | `FormatNegotiatorMiddleware` |
| `ATTRIBUTE_USERNAME` | `altair:http:username` | `DigestAuthenticationMiddleware` |
| `ATTRIBUTE_EXCEPTION` | `altair:http:exception` | `ExceptionHandlerMiddleware` |
| `ATTRIBUTE_CSRF_HEADER` | `X-XSRF-TOKEN` | (header name constant) |

Route segment variables (e.g. `{id}`) are also set as plain request attributes by `DispatcherMiddleware` and are visible to `InputParser`.

---

## Usage

### Building the middleware pipeline

`MiddlewareCollection` extends the `Altair\Structure\Queue`, so you push class names or objects onto it in the order you want them to run. `RelayConfiguration` then delegates `Relay` construction to a factory that reads the collection and injects a `ContainerResolver`.

```php
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Configuration\RelayConfiguration;
use Altair\Http\Middleware\DispatcherMiddleware;
use Altair\Http\Middleware\ActionMiddleware;
use Altair\Http\Middleware\ExceptionHandlerMiddleware;
use Altair\Http\Middleware\JsonContentMiddleware;

$queue = new MiddlewareCollection();
$queue->push(ExceptionHandlerMiddleware::class);
$queue->push(JsonContentMiddleware::class);
$queue->push(DispatcherMiddleware::class);
$queue->push(ActionMiddleware::class);

// Bind the queue in the container so RelayConfiguration can delegate to it.
$container->share(MiddlewareCollection::class, $queue);
(new RelayConfiguration())->apply($container);

$relay    = $container->make(\Relay\Relay::class);
$response = $relay->handle($request);
```

### Routing with FastRoute

Routes are declared as a `RouteCollection` map. Each entry's key is a `"METHOD /path"` string; the value is an `Action` instance. `FastRouteConfiguration` registers a `FastRoute\Dispatcher` factory with the container using `simpleDispatcher` (no file-based cache).

```php
use Altair\Http\Base\Action;
use Altair\Http\Collection\RouteCollection;
use Altair\Http\Configuration\FastRouteConfiguration;

$routes = new RouteCollection();
$routes->put('GET /users',          new Action(ListUsersDomain::class));
$routes->put('POST /users',         new Action(CreateUserDomain::class));
$routes->put('GET /users/{id:\d+}', new Action(ShowUserDomain::class));
$routes->put('DELETE /users/{id}',  new Action(
    DeleteUserDomain::class,
    DeleteUserResponder::class,  // custom responder
    DeleteUserInput::class,      // custom input
));

(new FastRouteConfiguration($routes))->apply($container);
```

If the path is not found, `DispatcherMiddleware` throws `HttpNotFoundException`. If the path is found but the method is not allowed, it throws `HttpMethodNotAllowedException`. Both extend `HttpException` and carry an HTTP status code; `ExceptionHandlerMiddleware` can capture them when constructed with `$capture = true`.

### Writing an Action

An `Action` is a value object. You instantiate it when registering routes. The only required argument is the Domain class name; Input and Responder default to `InputParser::class` and `CompoundResponder::class` respectively.

```php
// Minimal: use default Input and Responder.
new Action(CreateOrderDomain::class);

// Custom responder, default input.
new Action(CreateOrderDomain::class, CreateOrderResponder::class);

// Custom input and responder.
new Action(CreateOrderDomain::class, CreateOrderResponder::class, CreateOrderInput::class);
```

`ActionMiddleware` resolves each class name through the `ContainerResolver`, so you can type-hint constructor dependencies in your Domain, Input, and Responder classes and the DI container will inject them.

### Writing an Input

An Input extracts domain-neutral data from the request. The simplest Input you can write is a thin class that merges the data sources you care about into an `InputCollection`.

```php
use Altair\Http\Collection\InputCollection;
use Altair\Http\Contracts\InputInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CreateOrderInput implements InputInterface
{
    public function __construct(private readonly InputCollection $collection) {}

    public function __invoke(ServerRequestInterface $request): InputCollection
    {
        $body    = (array) $request->getParsedBody();
        $userId  = $request->getAttribute('user_id'); // from route or auth middleware

        return $this->collection->putAll([
            'items'   => $body['items'] ?? [],
            'user_id' => $userId,
        ]);
    }
}
```

The built-in `InputParser` merges request attributes, parsed body, cookies, query params, and uploaded files into one flat collection. Extend or replace it when you need a different merge strategy or when you want to perform initial validation before the domain sees the data.

### Writing a Domain

A Domain is a plain invokable class that receives an `InputCollection` and returns a `PayloadInterface`. It has no dependency on any HTTP class.

```php
use Altair\Http\Base\Payload;
use Altair\Http\Collection\InputCollection;
use Altair\Http\Contracts\DomainInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\PayloadInterface;

final class CreateOrderDomain implements DomainInterface
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function __invoke(InputCollection $input): PayloadInterface
    {
        $payload = new Payload();

        $items = $input->get('items', []);
        if (empty($items)) {
            return $payload
                ->withStatus(HttpStatusCodeInterface::HTTP_UNPROCESSABLE_ENTITY)
                ->withMessages(['items' => 'At least one item is required.']);
        }

        $order = $this->orders->create([
            'user_id' => $input->get('user_id'),
            'items'   => $items,
        ]);

        return $payload
            ->withStatus(HttpStatusCodeInterface::HTTP_CREATED)
            ->withOutput(['order' => $order->toArray()]);
    }
}
```

The `Payload` is fully immutable. Every `with*()` call returns a new instance; the original is never modified.

### Writing a Responder

A Responder translates a `PayloadInterface` into an HTTP response. Use one of the built-in responders unless you need custom response logic.

```php
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CreateOrderResponder implements ResponderInterface
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload,
    ): ResponseInterface {
        $response = $response->withStatus($payload->getStatus() ?? 500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(
            $payload->getOutput() + ['messages' => $payload->getMessages()],
            JSON_THROW_ON_ERROR,
        ));

        return $response;
    }
}
```

#### Built-in Responders

**`CompoundResponder`** is the default. It chains three built-in responders in order:

1. `FormattedResponder` — if the payload has output, selects a formatter via content negotiation and writes the response body.
2. `RedirectResponder` — if the payload has a `redirect` setting, adds a `Location` header.
3. `StatusResponder` — maps the payload's domain status to an HTTP status code on the response.

You can change the list of inner responders by passing a custom `$responders` array to the constructor.

**`FormattedResponder`** uses `willdurand/negotiation` to select the best formatter from the `Accept` header. It ships with `JsonFormatter` (accepts `application/json`) registered at priority `1.0`. Add `PhpViewFormatter` at priority `1.0` via `PhpViewConfiguration` for HTML responses.

**`RedirectResponder`** reads `$payload->getSetting('redirect')`. If the setting is present, it returns the response with a `Location` header. Combine it with a status code like `302` via `StatusResponder`.

**`StatusResponder`** reads `$payload->getStatus()` and sets the matching HTTP status code on the response using `HttpStatusCollection`.

### Built-in middleware

**`ExceptionHandlerMiddleware`** wraps the rest of the pipeline in a try/catch. In capture mode (`$capture = true`) it converts any `Throwable` into an error response via the `ErrorHandlerInterface`. In non-capture mode it re-throws. It also intercepts 4xx/5xx responses from the pipeline and routes them through the error handler. Use `DefaultErrorHandler` for development; replace it with a custom implementation for production.

**`DispatcherMiddleware`** calls the FastRoute dispatcher with the request method and path. On `FOUND`, it sets the `Action` on the request attribute `ATTRIBUTE_ACTION` and adds route segment variables as individual attributes. On `NOT_FOUND` or `METHOD_NOT_ALLOWED`, it throws a typed `HttpException`.

**`ActionMiddleware`** reads the `Action` from the request attribute, resolves the three collaborators through the container, and runs the full Input → Domain → Responder cycle.

**`JsonContentMiddleware`** parses request bodies with a `Content-Type` of `application/json`, `text/json`, or `application/x-json`. It throws `HttpBadRequestException` (400) on malformed JSON. Configurable: pass `$associative`, `$maxDepth`, and `$flags` to the constructor.

**`FormContentMiddleware`** parses `application/x-www-form-urlencoded` request bodies using `parse_str`.

**`FormatNegotiatorMiddleware`** inspects the request URI extension first (e.g. `/api/users.json` resolves to `json`) and falls back to the `Accept` header. It sets the resolved format string on the `ATTRIBUTE_FORMAT` request attribute and adds a `Content-Type` header to the response if one is not already present.

**`CorsMiddleware`** delegates to a `neomerx/cors-psr7 ^3` analyzer. Pre-flight `OPTIONS` requests are handled without calling the inner handler. Forbidden origins, missing `Host` headers, and unsupported methods return 403. Use `CorsMiddlewareConfiguration` to wire the neomerx `Settings` strategy.

**`CsrfMiddleware`** validates a `_csrf` field on unsafe HTTP methods (POST, PUT, PATCH, DELETE). On HTML responses it injects the token into every `<form method="POST">` element automatically. The token is managed by `Altair\Session\SessionManager`.

**`SessionHeadersMiddleware`** reads the session ID from the incoming cookie, sets it via `session_id()` before the inner pipeline runs, and writes the updated session cookie back on the response if the ID changed. It also applies a `CacheLimiterInterface` (default: `NoCacheLimiter`) to the response.

**`CacheMiddleware`** implements HTTP cache validation using PSR-6 storage. It sets `Cache-Control`, `Last-Modified`, and checks `ETag`/`If-None-Match`. Cacheable responses are stored; 304 responses are served from cache when `If-None-Match` or `If-Modified-Since` match.

**`IpAddressMiddleware`** extracts client IP addresses from `REMOTE_ADDR` and configurable forwarding headers, then stores the list as `ATTRIBUTE_IP_ADDRESS` on the request.

**`IpRestrictionMiddleware`** reads the IP list set by `IpAddressMiddleware` and enforces CIDR-based allow/deny rules. Must run after `IpAddressMiddleware`.

**`SpamBlockerMiddleware`** reads a plain-text domain blocklist from a file path (one domain per line) and returns 403 when the `Referer` header's host matches a blocked domain. Configure the path via `SpamBlockerMiddlewareConfiguration` and the `HTTP_SPAMMERS_FILE_PATH` environment variable.

**`BasicAuthenticationMiddleware`** enforces HTTP Basic authentication. It reads credentials from `PHP_AUTH_USER`/`PHP_AUTH_PWD` (or from the `HTTP_AUTHORIZATION` header) and calls an `IdentityValidatorInterface` with a `['user' => ..., 'password' => ...]` array. Returns 401 with a `WWW-Authenticate: Basic` header on failure.

**`DigestAuthenticationMiddleware`** enforces HTTP Digest authentication. It parses the `Authorization: Digest` header, passes the parsed fields and realm to a `DigestSignatureValidator`, and returns 401 with a `WWW-Authenticate: Digest` challenge on failure. Successfully authenticated requests have the username set as `ATTRIBUTE_USERNAME`.

### Authentication and JWT

`TokenAuthenticationMiddleware` is the most flexible authentication middleware. It attempts two authentication strategies in order:

1. If a token string is found via `TokenExtractorInterface`, it creates a `TokenInterface` via `TokenFactoryInterface::fromTokenString()`.
2. If no token is found but credentials are found via `CredentialsExtractorInterface`, it validates them with `IdentityValidatorInterface` and creates a token via `TokenFactoryInterface::fromCredentials()`.

Two extractors ship out of the box: `HeaderTokenExtractor` reads the `Authorization` header and strips the `Bearer ` prefix; `QueryParamsTokenExtractor` reads a named query parameter. On success, the resolved `TokenInterface` is stored on the request as `TokenInterface::TOKEN_KEY` (`altair:http:token`).

The JWT implementation uses `lcobucci/jwt`. `LcobucciTokenGenerator` generates signed tokens (RSA/SHA-256 by default). `LcobucciTokenParser` verifies the signature and returns a `Token` value object containing the raw JWT string and its claims as metadata.

Authentication rules control which requests the middleware applies to. `RequestMethodRule` passes through `OPTIONS` requests by default. `RequestPathRule` can restrict authentication to specific path prefixes while exempting others. Both implement `HttpAuthRuleInterface`. Pass an array of rules as the `$rules` constructor argument; if you pass an empty array the default `RequestMethodRule` is used.

All three authentication middleware enforce HTTPS by default. Requests over plain HTTP are rejected with a `RuntimeException` unless the host is in the configured `$allowed` list (default: `localhost`, `127.0.0.1`, `::1`).

### Error handling

Position `ExceptionHandlerMiddleware` as the outermost middleware in the queue. It wraps the rest of the pipeline:

```php
// Capture mode: converts all Throwable to error responses.
$queue->push(new ExceptionHandlerMiddleware(
    responseFactory: new ResponseFactory(),
    handler: new MyProductionErrorHandler(),
    capture: true,
));
```

The `ErrorHandlerInterface` receives the request (with the exception stored as `ATTRIBUTE_EXCEPTION`) and a pre-built response at the appropriate status code. `DefaultErrorHandler` content-negotiates the error format (HTML, JSON, XML, plain text, or an image) from the response's `Content-Type` header; it is suitable for development but uses `echo` internally and is not appropriate for production JSON APIs.

`HttpNotFoundException` and `HttpMethodNotAllowedException` carry HTTP status codes (404 and 405 respectively) and are thrown by `DispatcherMiddleware`. When `ExceptionHandlerMiddleware` is in capture mode, these are caught and forwarded to the error handler with the correct status.

---

## Configuration

Each `Configuration` class implements `ConfigurationInterface` from `univeros/configuration` and is applied against an `Altair\Container\Container` instance.

**`HttpMessageConfiguration`** aliases `RequestInterface`, `ServerRequestInterface`, and `ResponseInterface` to their Laminas Diactoros implementations. It also delegates `ServerRequest` construction to `ServerRequestFactory::fromGlobals()`, so `$container->make(ServerRequestInterface::class)` returns a request populated from `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, and `$_FILES`.

**`RelayConfiguration`** wires `ContainerResolver` with the container instance and delegates `Relay\Relay` construction to a factory that reads `MiddlewareCollection` from the container. You must push your middleware into `MiddlewareCollection` before applying this configuration.

**`FastRouteConfiguration`** registers a `FastRoute\Dispatcher` factory using `FastRoute\simpleDispatcher`. It iterates the `RouteCollection`, splits each key on the first space to get the HTTP method and path, and adds them to the FastRoute collector. There is no file-based route cache; if you need caching use `cachedDispatcher` and replace the factory.

**`PayloadConfiguration`** aliases `PayloadInterface` to `Payload`. This is required for the container to inject `PayloadInterface` automatically.

**`FormatNegotiatorMiddlewareConfiguration`** aliases `FormatNegotiatorInterface` to `FormatNegotiator`. Apply this before adding `FormatNegotiatorMiddleware` to the queue.

**`CorsMiddlewareConfiguration`** wires the neomerx CORS components. You must provide your own `Neomerx\Cors\Strategies\Settings` instance (configured with allowed origins, methods, and headers) and bind it as `AnalysisStrategyInterface` before or after applying this configuration.

**`SessionHeadersMiddlewareConfiguration`** aliases `CacheLimiterInterface` to `NoCacheLimiter` (no cache-control headers for session responses) and sets `session.use_trans_sid = 0`, `session.use_cookies = 0`, `session.use_only_cookies = 1` via `ini_set`. Apply this before adding `SessionHeadersMiddleware`.

**`LcobucciTokenConfiguration`** wires the JWT token components. It reads the following environment variables:

| Variable | Default | Description |
|---|---|---|
| `TOKEN_PUBLIC_KEY` | `'YOU_SHOULD_CHANGE_THIS'` | RSA public key (or HMAC secret for symmetric algos) |
| `TOKEN_PRIVATE_KEY` | `null` | RSA private key; required for token generation |
| `TOKEN_TTL` | `session.gc_maxlifetime` | Token lifetime in seconds |

It aliases `TokenConfigurationInterface` to `TokenConfiguration`, and registers the Lcobucci builder, parser, encoder, decoder, and validator aliases.

**`SpamBlockerMiddlewareConfiguration`** reads `HTTP_SPAMMERS_FILE_PATH` from the environment and passes it as the `$path` constructor argument to `SpamBlockerMiddleware`.

**`PhpViewConfiguration`** enables PHP template rendering. It reads two environment variables:

| Variable | Description |
|---|---|
| `HTTP_PHP_VIEW_TEMPLATE_PATH` | Absolute path to the templates directory |
| `HTTP_PHP_VIEW_LAYOUT` | Optional default layout template name |

It also prepares `FormattedResponder` by adding `PhpViewFormatter` at priority `1.0` via `$responder->withFormatter()`.

---

## Testing

### Testing a Domain in isolation

The Domain is the easiest class to test because it has no dependency on HTTP. Construct an `InputCollection`, call the domain, and assert on the returned `Payload`.

```php
use Altair\Http\Base\Payload;
use Altair\Http\Collection\InputCollection;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use PHPUnit\Framework\TestCase;

final class CreateOrderDomainTest extends TestCase
{
    public function testReturnsUnprocessableEntityWhenItemsAreEmpty(): void
    {
        $domain  = new CreateOrderDomain($this->createMock(OrderRepository::class));
        $input   = new InputCollection();
        $input->put('items', []);

        $payload = $domain($input);

        $this->assertSame(HttpStatusCodeInterface::HTTP_UNPROCESSABLE_ENTITY, $payload->getStatus());
        $this->assertArrayHasKey('items', $payload->getMessages());
    }
}
```

### Testing an Input in isolation

Construct a `ServerRequest`, call the input, and assert on the returned `InputCollection`.

```php
use Altair\Http\Collection\InputCollection;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

final class CreateOrderInputTest extends TestCase
{
    public function testExtractsItemsFromParsedBody(): void
    {
        $request = (new ServerRequest())->withParsedBody(['items' => ['sku-1', 'sku-2']]);
        $input   = new CreateOrderInput(new InputCollection());

        $collection = $input($request);

        $this->assertSame(['sku-1', 'sku-2'], $collection->get('items'));
    }
}
```

### Testing middleware

Extend `AbstractMiddlewareTest` (from the tests suite) for a ready-made `dispatch()` helper that builds a `Relay\Relay` pipeline terminated by a 200 no-op handler.

```php
use Altair\Tests\Http\Middleware\AbstractMiddlewareTest;
use Altair\Http\Middleware\JsonContentMiddleware;
use Laminas\Diactoros\Stream;

final class JsonContentMiddlewareTest extends AbstractMiddlewareTest
{
    public function testParsesJsonBody(): void
    {
        $stream = $this->stream('{"foo":"bar"}');
        $request = $this->request()
            ->withMethod('POST')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->dispatch([new JsonContentMiddleware()], $request);

        $this->assertSame(200, $response->getStatusCode());
    }
}
```

### Testing the full Action cycle

Wire a real pipeline with a mock Domain to verify that the Input, Domain, and Responder collaborate correctly without touching the database.

```php
use Altair\Http\Base\Action;
use Altair\Http\Base\Payload;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Middleware\ActionMiddleware;
use Altair\Http\Middleware\DispatcherMiddleware;
use Altair\Tests\Http\Middleware\AbstractMiddlewareTest;

final class CreateOrderActionTest extends AbstractMiddlewareTest
{
    public function testReturnsCreatedOnSuccess(): void
    {
        // Use a resolver that returns pre-built instances keyed by class name.
        $resolver = function (string $class) use (&$domain): object {
            return match ($class) {
                CreateOrderDomain::class  => $domain,
                default                   => new $class(),
            };
        };

        $domain  = $this->createMock(CreateOrderDomain::class);
        $domain->method('__invoke')->willReturn(
            (new Payload())->withStatus(HttpStatusCodeInterface::HTTP_CREATED)->withOutput(['id' => 1]),
        );

        // Build a minimal FastRoute dispatcher for the test.
        $dispatcher = \FastRoute\simpleDispatcher(static function (\FastRoute\RouteCollector $r): void {
            $r->addRoute('POST', '/orders', new Action(CreateOrderDomain::class));
        });

        $request  = $this->request('/orders')->withMethod('POST');
        $response = $this->dispatch(
            [new DispatcherMiddleware($dispatcher), new ActionMiddleware($resolver, $this->responseFactory())],
            $request,
        );

        $this->assertSame(201, $response->getStatusCode());
    }
}
```

---

## Extending

### Custom middleware

Implement `Altair\Http\Contracts\MiddlewareInterface` (which extends `Psr\Http\Server\MiddlewareInterface`). Use constructor injection for dependencies; the `ContainerResolver` will satisfy them from the DI container when the middleware class name is pushed onto the queue.

```php
use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->limiter->allow($request->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS))) {
            return $this->responseFactory->createResponse(429);
        }

        return $handler->handle($request);
    }
}
```

### Custom Responders

Implement `Altair\Http\Contracts\ResponderInterface` and register it in the route definition:

```php
new Action(MyDomain::class, MyCustomResponder::class);
```

To add a responder to the `CompoundResponder` chain, pass a custom `$responders` array:

```php
new CompoundResponder(
    $resolver,
    [MyHeaderResponder::class, FormattedResponder::class, StatusResponder::class],
);
```

### Custom output formatters

Implement `Altair\Http\Contracts\OutputFormatterInterface` — three methods: `accepts(): array` (MIME types), `type(): string` (Content-Type value), and `body(PayloadInterface $payload): string`. Register it on `FormattedResponder` via the `withFormatter()` method or via `PhpViewConfiguration::apply()` as a model for your own configuration class.

### Custom error handlers

Implement `Altair\Http\Contracts\ErrorHandlerInterface` and pass an instance to `ExceptionHandlerMiddleware`. The handler receives the request (with `ATTRIBUTE_EXCEPTION` set) and a response at the correct status code, and must return a `ResponseInterface`.

---

## Recipes

### REST resource Action

A complete resource with a custom input that validates required fields.

```php
// Route
$routes->put('POST /articles', new Action(
    CreateArticleDomain::class,
    JsonApiResponder::class,
    CreateArticleInput::class,
));

// Input
final class CreateArticleInput implements InputInterface
{
    public function __construct(private readonly InputCollection $collection) {}

    public function __invoke(ServerRequestInterface $request): InputCollection
    {
        $body = (array) $request->getParsedBody();

        return $this->collection->putAll([
            'title'   => $body['title'] ?? null,
            'body'    => $body['body'] ?? null,
            'user_id' => $request->getAttribute('user_id'),
        ]);
    }
}

// Domain
final class CreateArticleDomain implements DomainInterface
{
    public function __construct(private readonly ArticleRepository $repo) {}

    public function __invoke(InputCollection $input): PayloadInterface
    {
        $payload = new Payload();

        if (empty($input->get('title'))) {
            return $payload
                ->withStatus(HttpStatusCodeInterface::HTTP_UNPROCESSABLE_ENTITY)
                ->withMessages(['title' => 'Title is required.']);
        }

        $article = $this->repo->create($input->toArray());

        return $payload
            ->withStatus(HttpStatusCodeInterface::HTTP_CREATED)
            ->withOutput(['article' => $article->toArray()]);
    }
}
```

### Redirect after POST

Store the redirect URL in the payload settings and let `RedirectResponder` handle the `Location` header.

```php
return $payload
    ->withStatus(HttpStatusCodeInterface::HTTP_SEE_OTHER)   // 303
    ->withSetting('redirect', '/dashboard');
```

### JWT-protected endpoint

Place `TokenAuthenticationMiddleware` before `DispatcherMiddleware` on paths that require authentication, or use `RequestPathRule` to scope it:

```php
use Altair\Http\Middleware\TokenAuthenticationMiddleware;
use Altair\Http\Rule\RequestPathRule;
use Altair\Http\Support\HeaderTokenExtractor;

$auth = new TokenAuthenticationMiddleware(
    tokenExtractor:       new HeaderTokenExtractor(),
    credentialsExtractor: $container->make(BodyCredentialsExtractor::class),
    tokenFactory:         $container->make(MyTokenFactory::class),
    identityValidator:    $container->make(UserIdentityValidator::class),
    responseFactory:      new ResponseFactory(),
    rules: [
        new RequestPathRule(['path' => ['/api'], 'passthrough' => ['/api/login']]),
    ],
);

$queue->push($auth);
```

After successful authentication, downstream middleware and Actions can read the token via:

```php
$token = $request->getAttribute(TokenInterface::TOKEN_KEY);
$userId = $token->getMetadata('sub');
```

### Content-negotiated responder

`FormattedResponder` negotiates automatically. To add an XML formatter alongside JSON:

```php
use Altair\Http\Responder\FormattedResponder;

$responder = $container->make(FormattedResponder::class)
    ->withFormatter(XmlFormatter::class, 0.9);
```

`XmlFormatter` must implement `OutputFormatterInterface` and declare the XML MIME types in its `accepts()` method.

### PHP view template response

Apply `PhpViewConfiguration` to register `PhpViewFormatter`. In your Responder or Domain, set the `template` setting on the payload:

```php
return $payload
    ->withStatus(HttpStatusCodeInterface::HTTP_OK)
    ->withOutput(['user' => $user->toArray()])
    ->withSetting('template', 'users/show');  // resolves to $templatePath/users/show.php
```

Optionally override the layout per response:

```php
    ->withSetting('layout', 'layouts/main');
```

### Paginated JSON response

Return the total count and pagination metadata in the payload output:

```php
return $payload
    ->withStatus(HttpStatusCodeInterface::HTTP_OK)
    ->withOutput([
        'data'  => $page->items(),
        'meta'  => [
            'total' => $page->total(),
            'page'  => $page->currentPage(),
            'limit' => $page->perPage(),
        ],
    ]);
```

`FormattedResponder` serializes the full `getOutput()` array into the JSON body.

---

## Related packages

- [./cookie.md](./cookie.md) — PSR-7-aware cookie value objects. `SessionHeadersMiddleware` uses `CookieManager` directly.
- **session** — Session handlers (file, Redis, Mongo). `CsrfMiddleware` and `SessionHeadersMiddleware` require `Altair\Session\SessionManager`.
- **sanitation** — Input sanitation rules. Apply to the `InputCollection` inside your `InputInterface` implementation.
- **security** — Hashing, encryption, and CSRF token generation. `SecurityManager`'s CSRF token is what `CsrfMiddleware` validates.
- **validation** — Validation rules and middleware. Validate the `InputCollection` returned by your Input before the Domain processes it.
- **middleware** — Generic PSR-15 middleware primitives shared across packages. `Altair\Http\Contracts\MiddlewareInterface` extends the PSR-15 interface defined there.
- **container** — DI container (PSR-11). `ContainerResolver` wraps it. All middleware, Domains, Inputs, and Responders are resolved through it.

---

## Migration notes

### PSR-15 single-pass (relay/relay v2)

The most important breaking change in the Http package is the move from double-pass to single-pass PSR-15 middleware.

**Old double-pass signature (relay/relay v1, now removed):**

```php
// DO NOT write this. relay/middleware is gone in Relay 2.
public function __invoke(
    ServerRequestInterface $request,
    ResponseInterface $response,
    callable $next,
): ResponseInterface {
    $response = $next($request, $response);
    return $response->withHeader('X-Foo', 'bar');
}
```

**New single-pass signature (relay/relay v2, PSR-15):**

```php
// Correct PSR-15 middleware.
public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler,
): ResponseInterface {
    $response = $handler->handle($request);
    return $response->withHeader('X-Foo', 'bar');
}
```

Key differences:

- The method name is `process`, not `__invoke`.
- There is no `$response` parameter on the way in. Create fresh responses from `ResponseFactoryInterface` via constructor injection.
- The next step is `$handler->handle($request)`, not `$next($request, $response)`.
- Middleware that need to create a response (auth, CORS, error handler) receive a `ResponseFactoryInterface` via the constructor.

**`RelayBuilder` is gone.** `Relay\Relay` v2 accepts the queue directly in its constructor: `new Relay($queue, $resolver)`. The resolver is any callable that accepts a class name or object and returns an object — `ContainerResolver` fulfils this contract without implementing the removed `Relay\ResolverInterface`.

**`relay/middleware` is gone.** `AbstractContentHandlerMiddleware`, `FormContentMiddleware`, and `JsonContentMiddleware` no longer extend any Relay v1 abstract class. They are reimplemented inline as `Altair\Http\Middleware\AbstractContentHandlerMiddleware`.

**Pipeline construction changed.** Previously `RelayBuilder::newInstance($queue)->build()` returned a callable. Now `Relay::handle($request)` accepts a `ServerRequestInterface` directly. The `RelayConfiguration` handles this wiring automatically.

If you have any custom middleware that extends `Relay\MiddlewareInterface` or the old v1 abstract classes, update them to implement `Psr\Http\Server\MiddlewareInterface` with a `process()` method.

---

## Limitations

The Http package deliberately excludes several concerns:

- **HTTP/2 server push** — Not supported, and largely moot (server push has been removed from major browsers); the package stays at the message-oriented PSR-7/PSR-15 level. Note that server-sent events *are* achievable over PSR-15 — Observatory's `ActivityStreamHandler` streams an SSE tail through an emit-and-close handler — they are simply not shipped as a built-in Http helper.
- **WebSockets** — WebSocket connections require a protocol upgrade and persistent connection handling outside the PSR-15 request/response cycle.
- **Route caching** — `FastRouteConfiguration` uses `simpleDispatcher`, which recompiles routes on every request. For high-traffic applications, replace the factory with `cachedDispatcher` and a file path.
- **Rate limiting** — `RateLimitMiddleware` is a fixed-window PSR-15 limiter backed by any PSR-16 cache pool (`Altair\Cache` works out of the box). The default `IpKeyResolver` keys on the client IP, preferring the `ATTRIBUTE_IP_ADDRESS` attribute set by `IpAddressMiddleware` (so trusted-proxy resolution lives in one place — never trust `X-Forwarded-For` directly); pass a custom `KeyResolverInterface` for API-key or user-id keying. Under-limit requests pass through with informational `X-RateLimit-Limit / Remaining / Reset` headers; at-limit returns `429 Too Many Requests` with `Retry-After`. Fixed-window has the classic boundary burst (`2 × limit` across the window edge); layer a token-bucket on top if you need stricter accounting. Complements edge / reverse-proxy rate limiting; does not replace it.
- **Request body streaming** — `JsonContentMiddleware` and `FormContentMiddleware` buffer the entire body string via `(string) $request->getBody()`. They are not suitable for very large request bodies.
- **Multipart form data** — File upload parsing relies on PHP's built-in `$_FILES` superglobal via `ServerRequestFactory::fromGlobals()`. Complex multipart handling is outside the package's scope.
