# univeros/http  ·  Altair\Http

**Purpose:** PSR-15 HTTP foundation with the Action/Domain/Input/Responder lifecycle, FastRoute routing, content negotiation, and JWT authentication.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `CacheLimiterInterface` | `apply(ResponseInterface)` | `ResponseInterface` | constants: `EXPIRED` |
| `CredentialsExtractorInterface` | `extract(ServerRequestInterface)` | `array\|null` |  |
| `DomainInterface` | `__invoke(InputCollection)` | `PayloadInterface` |  |
| `ErrorHandlerInterface` | `__invoke(ServerRequestInterface, ResponseInterface)` | `ResponseInterface` |  |
| `ErrorLoggerInterface` | `getLogger()` | `LoggerInterface` |  |
|  | `log(mixed)` | `void` |  |
| `FormatNegotiatorInterface` | `getContentTypeByFormat(string)` | `string` | constants: `DEFAULT_FORMAT` |
|  | `getFromServerRequestAttribute(ServerRequestInterface)` | `string\|null` |  |
|  | `getFromServerRequestHeaderLine(ServerRequestInterface)` | `string\|null` |  |
|  | `getFromServerRequestUriPath(ServerRequestInterface)` | `string\|null` |  |
| `HttpAuthRuleInterface` | `__invoke(ServerRequestInterface)` | `bool` |  |
| `HttpExceptionInterface` | `getHeaders()` | `array` |  |
|  | `getStatusCode()` | `int` |  |
| `HttpStatusCodeInterface` | _(marker)_ |  | constants: `HTTP_ACCEPTED`, `HTTP_ALREADY_REPORTED`, `HTTP_BAD_GATEWAY`, `HTTP_BAD_REQUEST`, `HTTP_CONFLICT`, `HTTP_CONTINUE`, `HTTP_CREATED`, `HTTP_EXPECTATION_FAILED`, `HTTP_FAILED_DEPENDENCY`, `HTTP_FORBIDDEN`, `HTTP_FOUND`, `HTTP_GATEWAY_TIMEOUT`, `HTTP_GONE`, `HTTP_IM_A_TEAPOT`, `HTTP_IM_USED`, `HTTP_INSUFFICIENT_STORAGE`, `HTTP_INTERNAL_SERVER_ERROR`, `HTTP_LENGTH_REQUIRED`, `HTTP_LOCKED`, `HTTP_LOOP_DETECTED`, `HTTP_MAX_RANGE`, `HTTP_METHOD_NOT_ALLOWED`, `HTTP_MIN_RANGE`, `HTTP_MISDIRECTED_REQUEST`, `HTTP_MOVED_PERMANENTLY`, `HTTP_MULTIPLE_CHOICES`, `HTTP_MULTI_STATUS`, `HTTP_NETWORK_AUTHENTICATION_REQUIRED`, `HTTP_NON_AUTHORITATIVE_INFORMATION`, `HTTP_NOT_ACCEPTABLE`, `HTTP_NOT_EXTENDED`, `HTTP_NOT_FOUND`, `HTTP_NOT_IMPLEMENTED`, `HTTP_NOT_MODIFIED`, `HTTP_NO_CONTENT`, `HTTP_OK`, `HTTP_PARTIAL_CONTENT`, `HTTP_PAYLOAD_TOO_LARGE`, `HTTP_PAYMENT_REQUIRED`, `HTTP_PERMANENT_REDIRECT`, `HTTP_PRECONDITION_FAILED`, `HTTP_PRECONDITION_REQUIRED`, `HTTP_PROCESSING`, `HTTP_PROXY_AUTHENTICATION_REQUIRED`, `HTTP_RANGE_NOT_SATISFIABLE`, `HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE`, `HTTP_REQUEST_TIMEOUT`, `HTTP_RESERVED`, `HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL`, `HTTP_RESET_CONTENT`, `HTTP_SEE_OTHER`, `HTTP_SERVICE_UNAVAILABLE`, `HTTP_SWITCHING_PROTOCOLS`, `HTTP_TEMPORARY_REDIRECT`, `HTTP_TOO_MANY_REQUESTS`, `HTTP_UNAUTHORIZED`, `HTTP_UNAVAILABLE_FOR_LEGAL_REASONS`, `HTTP_UNPROCESSABLE_ENTITY`, `HTTP_UNSUPPORTED_MEDIA_TYPE`, `HTTP_UPGRADE_REQUIRED`, `HTTP_URI_TOO_LONG`, `HTTP_USE_PROXY`, `HTTP_VARIANT_ALSO_NEGOTIATES`, `HTTP_VERSION_NOT_SUPPORTED`, `RESPONSE_CLASS_CLIENT_ERROR`, `RESPONSE_CLASS_INFORMATIONAL`, `RESPONSE_CLASS_REDIRECTION`, `RESPONSE_CLASS_SERVER_ERROR`, `RESPONSE_CLASS_SUCCESS` |
| `HttpStatusInterface` | _(marker)_ |  | constants: `HTTP_ACCEPTED`, `HTTP_ALREADY_REPORTED`, `HTTP_BAD_GATEWAY`, `HTTP_BAD_REQUEST`, `HTTP_CONFLICT`, `HTTP_CONTINUE`, `HTTP_CREATED`, `HTTP_EXPECTATION_FAILED`, `HTTP_FAILED_DEPENDENCY`, `HTTP_FORBIDDEN`, `HTTP_FOUND`, `HTTP_GATEWAY_TIMEOUT`, `HTTP_GONE`, `HTTP_IM_A_TEAPOT`, `HTTP_IM_USED`, `HTTP_INSUFFICIENT_STORAGE`, `HTTP_INTERNAL_SERVER_ERROR`, `HTTP_LENGTH_REQUIRED`, `HTTP_LOCKED`, `HTTP_LOOP_DETECTED`, `HTTP_METHOD_NOT_ALLOWED`, `HTTP_MISDIRECTED_REQUEST`, `HTTP_MOVED_PERMANENTLY`, `HTTP_MULTIPLE_CHOICES`, `HTTP_MULTI_STATUS`, `HTTP_NETWORK_AUTHENTICATION_REQUIRED`, `HTTP_NON_AUTHORITATIVE_INFORMATION`, `HTTP_NOT_ACCEPTABLE`, `HTTP_NOT_EXTENDED`, `HTTP_NOT_FOUND`, `HTTP_NOT_IMPLEMENTED`, `HTTP_NOT_MODIFIED`, `HTTP_NO_CONTENT`, `HTTP_OK`, `HTTP_PARTIAL_CONTENT`, `HTTP_PAYLOAD_TOO_LARGE`, `HTTP_PAYMENT_REQUIRED`, `HTTP_PERMANENT_REDIRECT`, `HTTP_PRECONDITION_FAILED`, `HTTP_PRECONDITION_REQUIRED`, `HTTP_PROCESSING`, `HTTP_PROXY_AUTHENTICATION_REQUIRED`, `HTTP_RANGE_NOT_SATISFIABLE`, `HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE`, `HTTP_REQUEST_TIMEOUT`, `HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL`, `HTTP_RESET_CONTENT`, `HTTP_SEE_OTHER`, `HTTP_SERVICE_UNAVAILABLE`, `HTTP_SWITCHING_PROTOCOLS`, `HTTP_TEMPORARY_REDIRECT`, `HTTP_TOO_MANY_REQUESTS`, `HTTP_UNAUTHORIZED`, `HTTP_UNAVAILABLE_FOR_LEGAL_REASONS`, `HTTP_UNPROCESSABLE_ENTITY`, `HTTP_UNSUPPORTED_MEDIA_TYPE`, `HTTP_UPGRADE_REQUIRED`, `HTTP_URI_TOO_LONG`, `HTTP_USE_PROXY`, `HTTP_VARIANT_ALSO_NEGOTIATES`, `HTTP_VERSION_NOT_SUPPORTED` |
| `IdentityProviderInterface` | `findOneBy(array)` | `array\|null` |  |
| `IdentityValidatorInterface` | `__invoke(array)` | `mixed` |  |
| `InputInterface` | `__invoke(ServerRequestInterface)` | `InputCollection` |  |
| `MiddlewareInterface` | _(marker)_ |  | extends `MiddlewareInterface`; constants: `ATTRIBUTE_ACTION`, `ATTRIBUTE_CSRF_HEADER`, `ATTRIBUTE_EXCEPTION`, `ATTRIBUTE_FORMAT`, `ATTRIBUTE_IP_ADDRESS`, `ATTRIBUTE_USERNAME` |
| `OutputFormatterInterface` | `accepts()` | `array` |  |
|  | `body(PayloadInterface)` | `string` |  |
|  | `type()` | `string` |  |
| `PayloadInterface` | `getInputCollection()` | `InputCollection` | extends `HttpStatusInterface` |
|  | `getMessages()` | `array` |  |
|  | `getOutput()` | `array` |  |
|  | `getSetting(string, mixed)` | `mixed` |  |
|  | `getSettingsCollection()` | `SettingsCollection` |  |
|  | `getStatus()` | `int\|null` |  |
|  | `withInputCollection(InputCollection)` | `PayloadInterface` |  |
|  | `withMessages(array)` | `PayloadInterface` |  |
|  | `withOutput(array)` | `PayloadInterface` |  |
|  | `withSetting(string, mixed)` | `PayloadInterface` |  |
|  | `withSettingsCollection(SettingsCollection)` | `PayloadInterface` |  |
|  | `withStatus(int)` | `PayloadInterface` |  |
|  | `withoutSetting(string)` | `PayloadInterface` |  |
| `ProblemExtensionInterface` | `getProblemExtensions()` | `array` |  |
| `ResponderInterface` | `__invoke(ServerRequestInterface, ResponseInterface, PayloadInterface)` | `ResponseInterface` |  |
| `RouteInterface` | `getDomain()` | `mixed` |  |
|  | `getInput()` | `mixed` |  |
|  | `getResponder()` | `mixed` |  |
| `StatusCodeValidatorInterface` | `__invoke(int)` | `bool` |  |
| `TokenConfigurationInterface` | `getAudience()` | `string\|null` |  |
|  | `getExpirationTimestamp()` | `int` |  |
|  | `getIssuer()` | `string` |  |
|  | `getPrivateKey()` | `string\|null` |  |
|  | `getPublicKey()` | `string` |  |
|  | `getSigner()` | `Signer` |  |
|  | `getTimestamp()` | `int` |  |
|  | `getTtl()` | `int` |  |
| `TokenExtractorInterface` | `extract(ServerRequestInterface)` | `string\|null` |  |
| `TokenFactoryInterface` | `fromCredentials(array)` | `TokenInterface` |  |
|  | `fromTokenString(string)` | `TokenInterface` |  |
| `TokenGeneratorInterface` | `generate(array)` | `string` |  |
| `TokenInterface` | `getMetadata(string\|null)` | `mixed` | constants: `TOKEN_KEY` |
|  | `getToken()` | `string` |  |
| `TokenParserInterface` | `parse(string)` | `TokenInterface` |  |
| `TokenValidatorInterface` | `validate(string)` | `bool` |  |

## Concrete classes

- `AbstractCacheLimiter` _(abstract)_ — implements `CacheLimiterInterface`
- `AbstractContentHandlerMiddleware` _(abstract)_ — implements `MiddlewareInterface`
- `AbstractHtmlFormatter` _(abstract)_ — implements `OutputFormatterInterface`
- `Action`
- `ActionMiddleware` — implements `MiddlewareInterface`
- `ArrayIdentityValidator` — implements `IdentityValidatorInterface`
- `BasicAuthenticationMiddleware` — implements `MiddlewareInterface`
- `BodyCredentialsExtractor` — implements `CredentialsExtractorInterface`
- `CacheMiddleware` — implements `MiddlewareInterface`
- `CidrMatcher` _(final)_
- `CompoundResponder` — implements `ResponderInterface`
- `ContainerResolver`
- `CorsMiddleware` — implements `MiddlewareInterface`
- `CorsMiddlewareConfiguration` — implements `ConfigurationInterface`
- `CsrfMiddleware` — implements `MiddlewareInterface`
- `DefaultErrorHandler` — implements `ErrorHandlerInterface`
- `DigestAuthenticationMiddleware` — implements `MiddlewareInterface`
- `DigestSignatureValidator` — implements `IdentityValidatorInterface`
- `DispatcherMiddleware` — implements `MiddlewareInterface`
- `DtoInputHydrator` _(final)_
- `ExceptionHandlerMiddleware` — implements `MiddlewareInterface`
- `FastRouteConfiguration` — implements `ConfigurationInterface`
- `FormContentMiddleware` — implements `MiddlewareInterface`
- `FormatNegotiator` — implements `FormatNegotiatorInterface`
- `FormatNegotiatorMiddleware` — implements `MiddlewareInterface`
- `FormatNegotiatorMiddlewareConfiguration` — implements `ConfigurationInterface`
- `FormattedResponder` — implements `ResponderInterface`
- `HeaderTokenExtractor` — implements `TokenExtractorInterface`
- `HttpCache` _(final)_
- `HttpMessageConfiguration` — implements `ConfigurationInterface`
- `HttpStatusCollection` — implements `Countable`, `IteratorAggregate`, `Traversable`
- `InputCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `InputParser` — implements `InputInterface`
- `IpAddressMiddleware` — implements `MiddlewareInterface`
- `IpKeyResolver` _(final)_ — implements `KeyResolverInterface`
- `IpRestrictionMiddleware` — implements `MiddlewareInterface`
- `JsonContentMiddleware` — implements `MiddlewareInterface`
- `JsonFormatter` — implements `OutputFormatterInterface`
- `LcobucciTokenConfiguration` — implements `ConfigurationInterface`
- `LcobucciTokenGenerator` — implements `TokenGeneratorInterface`
- `LcobucciTokenParser` — implements `TokenParserInterface`
- `MiddlewareCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `QueueInterface`, `Stringable`, `Traversable`
- `MiddlewarePriority` _(final)_
- `MimeType`
- `ModuleMiddleware` _(final)_
- `ModuleRoutes` _(final)_
- `NoCacheLimiter` — implements `CacheLimiterInterface`
- `Payload` — implements `HttpStatusInterface`, `PayloadInterface`
- `PayloadConfiguration` — implements `ConfigurationInterface`
- `PhpViewConfiguration` — implements `ConfigurationInterface`
- `PhpViewFormatter` — implements `OutputFormatterInterface`
- `PrivateCacheLimiter` — implements `CacheLimiterInterface`
- `PrivateNoExpireCacheLimiter` — implements `CacheLimiterInterface`
- `ProblemDetailsErrorHandler` _(final)_ — implements `ErrorHandlerInterface`
- `PublicCacheLimiter` — implements `CacheLimiterInterface`
- `QueryParamsTokenExtractor` — implements `TokenExtractorInterface`
- `RateLimit` _(final)_
- `RateLimitMiddleware` _(final)_ — implements `MiddlewareInterface`
- `RedirectResponder` — implements `ResponderInterface`
- `RelayConfiguration` — implements `ConfigurationInterface`
- `RepositoryIdentityValidator` — implements `IdentityValidatorInterface`
- `RequestMethodRule` — implements `HttpAuthRuleInterface`
- `RequestPathRule` — implements `HttpAuthRuleInterface`
- `RouteCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `RouteFactory`
- `SessionHeadersMiddleware` — implements `MiddlewareInterface`
- `SessionHeadersMiddlewareConfiguration` — implements `ConfigurationInterface`
- `SettingsCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `SpamBlockerMiddleware` — implements `MiddlewareInterface`
- `SpamBlockerMiddlewareConfiguration` — implements `ConfigurationInterface`
- `StatusResponder` — implements `ResponderInterface`
- `SystemClock` _(final)_ — implements `ClockInterface`
- `Token` — implements `TokenInterface`
- `TokenAuthenticationMiddleware` — implements `MiddlewareInterface`
- `TokenConfiguration` _(final)_ — implements `TokenConfigurationInterface`

## Request attribute conventions

| Constant | Value | Declared on |
|---|---|---|
| `ATTRIBUTE_CSRF_HEADER` | `X-XSRF-TOKEN` | `MiddlewareInterface` |
| `ATTRIBUTE_ACTION` | `altair:http:action` | `MiddlewareInterface` |
| `ATTRIBUTE_EXCEPTION` | `altair:http:exception` | `MiddlewareInterface` |
| `ATTRIBUTE_FORMAT` | `altair:http:format` | `MiddlewareInterface` |
| `ATTRIBUTE_IP_ADDRESS` | `altair:http:ip-address` | `MiddlewareInterface` |
| `ATTRIBUTE_USERNAME` | `altair:http:username` | `MiddlewareInterface` |

## Tests as documentation

- `tests/Http/Base/PayloadTest.php`
- `tests/Http/Configuration/FastRouteConfigurationTest.php`
- `tests/Http/Exception/HttpExceptionStatusTest.php`
- `tests/Http/Formatter/JsonFormatterTest.php`
- `tests/Http/Input/DtoInputHydratorTest.php`
- `tests/Http/Jwt/LcobucciTokenGeneratorTest.php`
- `tests/Http/Jwt/LcobucciTokenParserTest.php`
- `tests/Http/Jwt/SystemClockTest.php`
- `tests/Http/Middleware/AbstractMiddlewareTest.php`
- `tests/Http/Middleware/ActionPipelineTest.php`
- `tests/Http/Middleware/CorsMiddlewareTest.php`
- `tests/Http/Middleware/DispatcherMiddlewareTest.php`
- `tests/Http/Middleware/ExceptionHandlerMiddlewareTest.php`
- `tests/Http/Middleware/FormContentMiddlewareTest.php`
- `tests/Http/Middleware/FormatNegotiatorMiddlewareTest.php`
- `tests/Http/Middleware/IpAddressMiddlewareTest.php`
- `tests/Http/Middleware/IpRestrictionMiddlewareTest.php`
- `tests/Http/Middleware/JsonContentMiddlewareTest.php`
- `tests/Http/Middleware/RateLimit/RateLimitMiddlewareTest.php`
- `tests/Http/Middleware/SpamBlockerMiddlewareTest.php`
- `tests/Http/Resolver/ContainerResolverTest.php`
- `tests/Http/Responder/RedirectResponderTest.php`
- `tests/Http/Rule/RequestMethodRuleTest.php`
- `tests/Http/Rule/RequestPathRuleTest.php`
- `tests/Http/Support/CacheLimiterTest.php`
- `tests/Http/Support/CidrMatcherTest.php`
- `tests/Http/Support/FormatNegotiatorTest.php`
- `tests/Http/Support/HeaderTokenExtractorTest.php`
- `tests/Http/Support/HttpCacheTest.php`
- `tests/Http/Support/MimeTypeTest.php`
- `tests/Http/Support/ModuleMiddlewareTest.php`
- `tests/Http/Support/ModuleRoutesTest.php`
- `tests/Http/Support/ProblemDetailsErrorHandlerTest.php`
- `tests/Http/Support/QueryParamsTokenExtractorTest.php`
- `tests/Http/Validator/DigestSignatureValidatorTest.php`
- `tests/Http/Validator/RepositoryIdentityValidatorTest.php`

## Related packages

- `laminas/laminas-diactoros`
- `lcobucci/clock`
- `lcobucci/jwt`
- `neomerx/cors-psr7`
- `nikic/fast-route`
- `psr/cache`
- `psr/http-factory`
- `psr/http-message`
- `psr/http-server-handler`
- `psr/http-server-middleware`
- `relay/relay`
- `univeros/cache`
- `univeros/configuration`
- `univeros/module`
- `univeros/session`
- `univeros/structure`
- `willdurand/negotiation`
