<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session;

use Altair\Security\Support\Salt;
use Altair\Session\Contracts\CsrfTokenInterface;
use Altair\Session\Contracts\SessionBlockInterface;
use Altair\Session\Contracts\SessionManagerInterface;
use Altair\Session\Exception\InvalidArgumentException;
use Altair\Session\Factory\SessionBlockFactory;
use Psr\Http\Message\ServerRequestInterface;
use SessionHandlerInterface;

class SessionManager implements SessionManagerInterface
{
    /**
     * Incoming cookies from the client, typically a copy of the $_COOKIE
     * superglobal.
     *
     * @var array
     */
    protected $cookies;
    /**
     * Session cookie parameters.
     *
     * @var array
     */
    protected $cookieParams = [];
    /**
     * A callable to invoke when deleting the session cookie. The callable
     * should have the following signature: `function ($cookie_name, $cookie_params)` and must return null;
     *
     * @var callable
     *
     * @see setDeleteCookie()
     */
    protected $deleteCookieCallable;
    /**
     * @var SessionHandlerInterface
     */
    protected $sessionHandler;
    /**
     * @var CsrfToken
     */
    protected $csrfToken;

    /**
     * SessionManager constructor.
     *
     * @param ServerRequestInterface $request
     * @param SessionHandlerInterface|null $sessionHandler
     * @param callable|null $deleteCookieCallable
     */
    public function __construct(
        ServerRequestInterface $request,
        SessionHandlerInterface $sessionHandler = null,
        callable $deleteCookieCallable = null
    ) {
        $this->cookies = $request->getCookieParams();
        $this->cookieParams = session_get_cookie_params();
        $this->sessionHandler = $sessionHandler;
        $this->setDeleteCookieCallable($deleteCookieCallable);

        register_shutdown_function([$this, 'close']);
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * @inheritDoc
     */
    public function setId(string $id)
    {
        session_id($id);
    }

    /**
     * @inheritDoc
     */
    public function setDeleteCookieCallable(callable $callable = null)
    {
        $this->deleteCookieCallable = $callable?? function ($name, $params) {
            $path = $params['path']?? null;
            $domain = $params['domain']?? null;
            setcookie($name, '', time() - 42000, $path, $domain);
        };
    }

    /**
     * @inheritDoc
     */
    public function getSessionBlock(string $name): SessionBlockInterface
    {
        return SessionBlockFactory::create($name, $this);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return session_name();
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name)
    {
        session_name($name);
    }

    /**
     * @inheritDoc
     */
    public function getSavePath(): string
    {
        return session_save_path();
    }

    /**
     * @inheritDoc
     */
    public function setSavePath(string $path)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(sprintf('Session save path is not a valid directory: %s', $path));
        }

        session_save_path($path);
    }

    /**
     * @inheritDoc
     */
    public function getCookieParams(): array
    {
        return $this->getCookieParams();
    }

    /**
     * @inheritDoc
     */
    public function setCookieParams(array $params)
    {
        $this->cookieParams = array_merge($this->cookieParams, $params);
        session_set_cookie_params(
            $this->cookieParams['lifetime'],
            $this->cookieParams['path'],
            $this->cookieParams['domain'],
            $this->cookieParams['secure'],
            $this->cookieParams['httponly']
        );
    }

    /**
     * @inheritDoc
     */
    public function getIsActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    public function exists(): bool
    {
        return isset($this->cookies[$this->getName()]);
    }

    /**
     * @inheritDoc
     */
    public function start(): bool
    {
        if (!$this->getIsActive()) {
            if ($this->sessionHandler !== null) {
                session_set_save_handler($this->sessionHandler, false);
            }

            return session_start();
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        session_unset();
    }

    /**
     * @inheritDoc
     */
    public function resume(): bool
    {
        if ($this->getIsActive()) {
            return true;
        }

        if ($this->exists()) {
            return $this->start();
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function close()
    {
        if ($this->getIsActive()) {
            session_write_close();
        }
    }

    /**
     * @inheritDoc
     */
    public function destroy(): bool
    {
        if (!$this->getIsActive()) {
            $this->start();
        }

        $this->clear();

        if (session_destroy()) {
            call_user_func($this->deleteCookieCallable, $this->getName(), $this->getCookieParams());

            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function regenerateId(bool $deletePrevious = true): bool
    {
        $result = false;

        if ($this->getIsActive()) {
            $result = session_regenerate_id($deletePrevious);
            if ($result && $this->csrfToken instanceof CsrfTokenInterface) {
                $this->csrfToken->generateValue();
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getCsrfToken(): CsrfTokenInterface
    {
        if ($this->csrfToken === null) {
            $sessionBlock = $this->getSessionBlock(SessionBlockInterface::CSRF_KEY);
            $this->csrfToken = new CsrfToken($sessionBlock, new Salt());
            $this->csrfToken->generateValue();
        }

        return $this->csrfToken;
    }
}
