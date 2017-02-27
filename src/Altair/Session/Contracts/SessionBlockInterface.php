<?php
namespace Altair\Session\Contracts;

interface SessionBlockInterface
{
    const CSRF_KEY = 'altair:session:csrf';
    const FLASH_KEY = 'altair:session:flash';

    /**
     * Returns the value of a key in the session block.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Sets the value of a key in the session block.
     *
     * @param string $key
     * @param $value
     *
     * @return SessionBlockInterface
     */
    public function set(string $key, $value): SessionBlockInterface;

    /**
     * Removes a key from the session block.
     *
     * @param string $key
     *
     * @return SessionBlockInterface
     */
    public function remove(string $key): SessionBlockInterface;

    /**
     * Checks whether the session block has a specific key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Clear all data from the segment.
     *
     * @return SessionBlockInterface
     */
    public function clear(): SessionBlockInterface;

    /**
     * Returns a flash message.
     *
     * @param string $key the key identifying the flash message
     * @param mixed $default value to be returned if the flash message does not exist.
     * @param bool $delete whether to delete this flash message right after this method is called.
     * If false, the flash message will be automatically deleted in the next request.
     *
     * @return mixed the flash message or an array of messages if addFlash was used
     */
    public function getFlash(string $key, $default = null, bool $delete = false);

    /**
     * Returns all flash messages.
     *
     * You may use this method to display all the flash messages in a view file:
     *
     * ```php
     * <?php
     * foreach ($sessionBlock->getAllFlashes() as $key => $message) {
     *     echo '<div class="alert alert-' . $key . '">' . $message . '</div>';
     * } ?>
     * ```
     * Note that if you use [[addFlash()]], `$message` will be an array, and you will have to adjust the above code.
     *
     * @param bool $delete whether to delete the flash messages right after this method is called.
     * If false, the flash messages will be automatically deleted in the next request.
     *
     * @return array flash messages (key => message or key => [message1, message2]).
     */
    public function getAllFlashes($delete = false);

    /**
     * Sets a flash message.
     * A flash message will be automatically deleted after it is accessed in a request and the deletion will happen
     * in the next request.
     * If there is already an existing flash message with the same key, it will be overwritten by the new one.
     *
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space. If you have a normal
     * session variable using the same name, its value will be overwritten by this method.
     * @param mixed $value flash message
     * @param bool $immediateRemoval whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     *
     * @return SessionBlockInterface
     */
    public function setFlash(string $key, $value = true, bool $immediateRemoval = true): SessionBlockInterface;

    /**
     * Appends a flash message. If there are existing flash messages with the same key, the new one will be appended to
     * the existing message array.
     *
     * @param string $key the key identifying the flash message.
     * @param mixed $value flash message
     * @param bool $immediateRemoval whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     *
     * @return SessionBlockInterface
     */
    public function appendFlash(string $key, $value = true, bool $immediateRemoval = true): SessionBlockInterface;

    /**
     * Removes a flash message.
     *
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space.  If you have a normal
     * session variable using the same name, it will be removed by this method.
     *
     * @return mixed the removed flash message. Null if the flash message does not exist.
     */
    public function removeFlash($key);

    /**
     * Removes all flash messages.
     * Note that flash messages and normal session variables share the same name space.
     * If you have a normal session variable using the same name, it will be removed
     * by this method.
     */
    public function removeAllFlashes();

    /**
     * Returns a value indicating whether there are flash messages associated with the specified key.
     *
     * @param string $key key identifying the flash message type
     *
     * @return bool whether any flash messages exist under specified key
     */
    public function hasFlash($key): bool;
}
