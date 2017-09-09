<?php
namespace Altair\Http\Middleware;

use Altair\Http\Base\Action;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Exception\HttpMethodNotAllowedException;
use Altair\Http\Exception\HttpNotFoundException;
use FastRoute\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DispatcherMiddleware implements MiddlewareInterface
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * DispatcherMiddleware constructor.
     *
     * @param Dispatcher $dispatcher
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        /**
         * @var $action Action
         */
        list($action, $args) = $this->dispatch($this->dispatcher, $request->getMethod(), $request->getUri()->getPath());
        $request = $request->withAttribute(MiddlewareInterface::ATTRIBUTE_ACTION, $action);
        foreach ($args as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $next($request, $response);
    }

    /**
     * @param Dispatcher $dispatcher
     * @param $method
     * @param $path
     *
     * @return array
     * @throws HttpMethodNotAllowedException
     * @throws HttpNotFoundException
     */
    private function dispatch(Dispatcher $dispatcher, $method, $path)
    {
        $route = $dispatcher->dispatch($method, $path);
        $status = array_shift($route);
        if (Dispatcher::FOUND === $status) {
            return $route;
        }
        if (Dispatcher::METHOD_NOT_ALLOWED === $status) {
            $allowed = array_shift($route);
            throw new HttpMethodNotAllowedException(
                $allowed,
                sprintf(
                    "Cannot access resource '%s' using method '%s'",
                    $path,
                    $method
                ),
                405
            );
        }
        throw new HttpNotFoundException($path);
    }
}
