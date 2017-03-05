<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\ErrorHandlerInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\StatusCodeValidatorInterface;
use Altair\Http\Support\DefaultErrorHandler;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ExceptionHandlerMiddleware implements MiddlewareInterface
{
    protected $handler;
    protected $validator;
    protected $capture;

    /**
     * ExceptionHandlerMiddleware constructor.
     *
     * @param ErrorHandlerInterface|null $handler
     * @param StatusCodeValidatorInterface|null $validator
     * @param bool $capture
     */
    public function __construct(
        ErrorHandlerInterface $handler = null,
        StatusCodeValidatorInterface $validator = null,
        bool $capture = false
    ) {
        $this->handler = $handler?? new DefaultErrorHandler();
        $this->validator = $validator;
        $this->capture = $capture;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        ob_start();
        $level = ob_get_level();
        $output = '';
        try {
            /** @var ResponseInterface $response */
            $response = $next($request, $response);

            return $this->getIsError($response->getStatusCode())
                ? $this->handleError($request, $response, null, $response->getStatusCode())
                : $response;
        } catch (Throwable $e) {
            if (!$this->capture) {
                throw $e;
            }

            return $this->handleError($request, $response, $e);
        } catch (Exception $e) {
            if (!$this->capture) {
                throw $e;
            }

            return $this->handleError($request, $response, $e);
        } finally {
            while (ob_get_level() >= $level) {
                $output .= ob_get_clean();
            }
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param null $exception
     * @param int $code
     *
     * @return ResponseInterface
     */
    protected function handleError(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $exception = null,
        $code = HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR
    ): ResponseInterface {
        $request = $request->withAttribute(MiddlewareInterface::ATTRIBUTE_EXCEPTION, $exception);

        return call_user_func($this->handler, $request, $response->withStatus($code));
    }

    /**
     * Checks whether a status code is an error code.
     *
     * @param int $code
     *
     * @return bool
     */
    protected function getIsError(int $code): bool
    {
        return null !== $this->validator
            ? call_user_func($this->validator, $code)
            : $code >= HttpStatusCodeInterface::HTTP_BAD_REQUEST && $code < HttpStatusCodeInterface::HTTP_MAX_RANGE;
    }
}
