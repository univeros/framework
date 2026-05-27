<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Contracts;

use Altair\Session\Exception\InvalidArgumentException;

interface SessionManagerInterface
{
    /**
     * Returns current session id.
     */
    public function getId(): string;

    /**
     * Sets current session id.
     */
    public function setId(string $id);

    /**
     * Sets the callable used to clear the session cookie when destroying the session.
     */
    public function setDeleteCookieCallable(?callable $callable = null);

    /**
     * Creates a session block.
     *
     *
     */
    public function getSessionBlock(string $name): SessionBlockInterface;

    /**
     * Returns current session name.
     */
    public function getName(): string;

    /**
     * Sets session name.
     */
    public function setName(string $name);

    /**
     * Gets the current session save path.
     * This is a wrapper for [session_save_path()](http://php.net/manual/en/function.session-save-path.php).
     * @return string the current session save path, defaults to '/tmp'.
     */
    public function getSavePath(): string;

    /**
     * Sets the current session save path.
     * This is a wrapper for [session_save_path()](http://php.net/manual/en/function.session-save-path.php).
     *
     * @param string $path the current session save path.
     *
     * @throws InvalidArgumentException if the path is not a valid directory
     */
    public function setSavePath(string $path);

    /**
     * Returns the session cookie params.
     */
    public function getCookieParams(): array;

    /**
     *
     * Sets the session cookie params.  Param array keys are:
     *
     * - `lifetime` : Lifetime of the session cookie, defined in seconds.
     *
     * - `path` : Path on the domain where the cookie will work.
     *   Use a single slash ('/') for all paths on the domain.
     *
     * - `domain` : Cookie domain, for example 'www.php.net'.
     *   To make cookies visible on all subdomains then the domain must be
     *   prefixed with a dot like '.php.net'.
     *
     * - `secure` : If TRUE cookie will only be sent over secure connections.
     *
     * - `httponly` : If set to TRUE then PHP will attempt to send the httponly
     *   flag when setting the session cookie.
     *
     * @param array $params The array of session cookie param keys and values.
     *
     * @see [session_set_cookie_params()](http://php.net/manual/es/function.session-set-cookie-params.php).
     */
    public function setCookieParams(array $params);

    /**
     * Returns whether the session has started or not.
     */
    public function getIsActive(): bool;

    /**
     * Checks whether a session is available or exists on request cookies.
     */
    public function exists(): bool;

    /**
     * Starts new or existing session.
     */
    public function start(): bool;

    /**
     * Clears all session variables across all session blocks.
     */
    public function clear();

    /**
     * Resumes a session but not starts a new one if not exists.
     */
    public function resume(): bool;

    /**
     * Writes all the session data from session blocks and ends the session.
     */
    public function close();

    /**
     * Destroys the session entirely.
     */
    public function destroy(): bool;

    /**
     * Regenerates and replaces current session id. If we have a CSRF token, it also regenerates it.
     *
     *
     */
    public function regenerateId(bool $deletePrevious = true): bool;

    /**
     * Returns the output CSRF token. If not set, will create one automatically.
     */
    public function getCsrfToken(): CsrfTokenInterface;
}
