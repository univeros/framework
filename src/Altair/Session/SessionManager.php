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
     * @inheritdoc
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * @inheritdoc
     */
    public function setId(string $id)
    {
        session_id($id);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function getSessionBlock(string $name): SessionBlockInterface
    {
        return SessionBlockFactory::create($name, $this);
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return session_name();
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name)
    {
        session_name($name);
    }

    /**
     * @inheritdoc
     */
    public function getSavePath(): string
    {
        return session_save_path();
    }

    /**
     * @inheritdoc
     */
    public function setSavePath(string $path)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(sprintf('Session save path is not a valid directory: %s', $path));
        }

        session_save_path($path);
    }

    /**
     * @inheritdoc
     */
    public function getCookieParams(): array
    {
        return $this->getCookieParams();
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * @inheritdoc
     */
    public function exists(): bool
    {
        return isset($this->cookies[$this->getName()]);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function clear()
    {
        session_unset();
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function close()
    {
        if ($this->getIsActive()) {
            session_write_close();
        }
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
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
