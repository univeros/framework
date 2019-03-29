<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session;

use Altair\Session\Contracts\SessionBlockInterface;
use Altair\Session\Contracts\SessionManagerInterface;

class SessionBlock implements SessionBlockInterface
{
    /**
     * @var string
     */
    protected $name;
    /**
     * @var SessionManagerInterface
     */
    protected $manager;

    /**
     * SessionBlock constructor.
     *
     * @param string $name
     * @param SessionManagerInterface $sessionManager
     */
    public function __construct(string $name, SessionManagerInterface $sessionManager)
    {
        $this->name = $name;
        $this->manager = $sessionManager;
        $this->resumeOrStartSession();
        $this->updateFlashCounters();
    }

    /**
     * @inheritdoc
     */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$this->name][$key] ?? $default;
    }

    /**
     * @inheritdoc
     */
    public function set(string $key, $value): SessionBlockInterface
    {
        $_SESSION[$this->name][$key] = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function remove(string $key): SessionBlockInterface
    {
        if ($this->has($key)) {
            unset($_SESSION[$this->name][$key]);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$this->name][$key]);
    }

    /**
     * @inheritdoc
     */
    public function clear(): SessionBlockInterface
    {
        $_SESSION[$this->name] = [];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFlash(string $key, $default = null, bool $delete = false)
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        if (isset($counters[$key])) {
            $value = $this->get($key, $default);
            if ($delete) {
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                $counters[$key] = 1; // delete in next request
                $this->set(SessionBlockInterface::FLASH_KEY, $counters);
            }

            return $value;
        }

        return $default;
    }

    /**
     * @inheritdoc
     */
    public function getAllFlashes($delete = false)
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        $flashes = [];
        foreach (array_keys($counters) as $key) {
            if ($this->has($key)) {
                $flashes[$key] = $this->get($key);
                if ($delete) {
                    unset($counters[$key]);
                    $this->remove($key);
                } elseif ($counters[$key] < 0) {
                    $counters[$key] = 1; // mark for deletion in the next request
                }
            } else {
                unset($counters[$key]);
            }
        }

        $this->set(SessionBlockInterface::FLASH_KEY, $counters);

        return $flashes;
    }

    /**
     * @inheritdoc
     */
    public function setFlash(string $key, $value = true, bool $immediateRemoval = true): SessionBlockInterface
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        $counters[$key] = $immediateRemoval ? -1 : 0;

        return $this
            ->set($key, $value)
            ->set(SessionBlockInterface::FLASH_KEY, $counters);
    }

    /**
     * @inheritdoc
     */
    public function appendFlash(string $key, $value = true, bool $immediateRemoval = true): SessionBlockInterface
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        $counters[$key] = $immediateRemoval ? -1 : 0;
        $this->set(SessionBlockInterface::FLASH_KEY, $counters);
        $original = $this->get($key);

        if (empty($original)) {
            $original = [$value];
        } else {
            if (is_array($original)) {
                $original[] = $value;
            } else {
                $original = [$original, $value];
            }
        }

        return $this->set($key, $original);
    }

    /**
     * @inheritdoc
     */
    public function removeFlash($key)
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        $value = $this->has($key) && isset($counters[$key]) ? $this->get($key) : null;
        unset($counters[$key]);
        $this
            ->remove($key)
            ->set(SessionBlockInterface::FLASH_KEY, $counters);

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function removeAllFlashes()
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        foreach (array_keys($counters) as $key) {
            $this->remove($key);
        }
        $this->remove(SessionBlockInterface::FLASH_KEY);
    }

    /**
     * @inheritdoc
     */
    public function hasFlash($key): bool
    {
        return $this->getFlash($key) !== null;
    }

    /**
     * Loads the segment only if the session has already been started, or if
     * a session is available (in which case it resumes the session first).
     *
     * @return bool
     */
    protected function resumeSession(): bool
    {
        if ($this->manager->getIsActive() || $this->manager->resume()) {
            $this->load();

            return true;
        }

        return false;
    }

    /**
     * Sets the segment properties to $_SESSION references.
     *
     * @return null
     */
    protected function load()
    {
        $_SESSION[$this->name] = $_SESSION[$this->name]?? [];
        $_SESSION[SessionBlockInterface::FLASH_KEY] = $_SESSION[SessionBlockInterface::FLASH_KEY] ?? [];
    }

    /**
     * Resumes a previous session, or starts a new one, and loads the segment.
     *
     * @return null
     */
    protected function resumeOrStartSession()
    {
        if (!$this->resumeSession()) {
            $this->manager->start();
            $this->load();
        }
    }

    /**
     * Updates the counters for flash messages and removes outdated flash messages.
     * This method should only be called once when the session block class is created.
     */
    protected function updateFlashCounters()
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key]);
                    $this->remove($key);
                } elseif ($count === 0) {
                    $counters[$key]++;
                }
            }
            $this->set(SessionBlockInterface::FLASH_KEY, $counters);
        }
    }
}
