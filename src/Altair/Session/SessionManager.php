<?php

declare(strict_types=1);

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
use Override;
use Psr\Http\Message\ServerRequestInterface;
use SessionHandlerInterface;

class SessionManager implements SessionManagerInterface
{
    /**
     * Incoming cookies from the client, typically a copy of the $_COOKIE
     * superglobal.
     *
     * @var array<string, mixed>
     */
    protected $cookies;

    /**
     * Session cookie parameters.
     *
     * @var array<string, mixed>
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
     * @var CsrfToken
     */
    protected $csrfToken;

    /**
     * SessionManager constructor.
     */
    public function __construct(
        ServerRequestInterface $request,
        protected ?SessionHandlerInterface $sessionHandler = null,
        ?callable $deleteCookieCallable = null
    ) {
        $this->cookies = $request->getCookieParams();
        $this->cookieParams = session_get_cookie_params();
        $this->setDeleteCookieCallable($deleteCookieCallable);

        register_shutdown_function([$this, 'close']);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getId(): string
    {
        $id = session_id();

        return $id === false ? '' : $id;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setId(string $id): void
    {
        session_id($id);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setDeleteCookieCallable(?callable $callable = null): void
    {
        $this->deleteCookieCallable = $callable ?? function ($name, array $params): void {
            $path = $params['path'] ?? null;
            $domain = $params['domain'] ?? null;
            setcookie($name, '', ['expires' => time() - 42000, 'path' => $path, 'domain' => $domain]);
        };
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getSessionBlock(string $name): SessionBlockInterface
    {
        return SessionBlockFactory::create($name, $this);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getName(): string
    {
        $name = session_name();

        return $name === false ? '' : $name;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setName(string $name): void
    {
        session_name($name);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getSavePath(): string
    {
        $path = session_save_path();

        return $path === false ? '' : $path;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setSavePath(string $path): void
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(\sprintf('Session save path is not a valid directory: %s', $path));
        }

        session_save_path($path);
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $params
     */
    #[Override]
    public function setCookieParams(array $params): void
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
    #[Override]
    public function getIsActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function exists(): bool
    {
        return isset($this->cookies[$this->getName()]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function start(): bool
    {
        if (!$this->getIsActive()) {
            if ($this->sessionHandler instanceof SessionHandlerInterface) {
                session_set_save_handler($this->sessionHandler, false);
            }

            return session_start();
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): void
    {
        session_unset();
    }

    /**
     * @inheritDoc
     */
    #[Override]
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
    #[Override]
    public function close(): void
    {
        if ($this->getIsActive()) {
            session_write_close();
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function destroy(): bool
    {
        if (!$this->getIsActive()) {
            $this->start();
        }

        $this->clear();

        if (session_destroy()) {
            \call_user_func($this->deleteCookieCallable, $this->getName(), $this->getCookieParams());

            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
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
    #[Override]
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
