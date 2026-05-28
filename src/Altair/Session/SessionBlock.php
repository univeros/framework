<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session;

use Altair\Session\Contracts\SessionBlockInterface;
use Altair\Session\Contracts\SessionManagerInterface;
use Override;

class SessionBlock implements SessionBlockInterface
{
    /**
     * SessionBlock constructor.
     */
    public function __construct(protected string $name, protected SessionManagerInterface $manager)
    {
        $this->resumeOrStartSession();
        $this->updateFlashCounters();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(string $key, mixed $default = null)
    {
        return $_SESSION[$this->name][$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function set(string $key, mixed $value): SessionBlockInterface
    {
        $_SESSION[$this->name][$key] = $value;

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(string $key): SessionBlockInterface
    {
        if ($this->has($key)) {
            unset($_SESSION[$this->name][$key]);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $key): bool
    {
        return isset($_SESSION[$this->name][$key]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): SessionBlockInterface
    {
        $_SESSION[$this->name] = [];

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
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
     * @inheritDoc
     * @return array<string, mixed>
     */
    #[Override]
    public function getAllFlashes($delete = false): array
    {
        $counters = $this->getFlashCounters();
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
     * @inheritDoc
     */
    #[Override]
    public function setFlash(string $key, $value = true, bool $immediateRemoval = true): SessionBlockInterface
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        $counters[$key] = $immediateRemoval ? -1 : 0;

        return $this
            ->set($key, $value)
            ->set(SessionBlockInterface::FLASH_KEY, $counters);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function appendFlash(string $key, $value = true, bool $immediateRemoval = true): SessionBlockInterface
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        $counters[$key] = $immediateRemoval ? -1 : 0;
        $this->set(SessionBlockInterface::FLASH_KEY, $counters);
        $original = $this->get($key);

        if (empty($original)) {
            $original = [$value];
        } elseif (\is_array($original)) {
            $original[] = $value;
        } else {
            $original = [$original, $value];
        }

        return $this->set($key, $original);
    }

    /**
     * @inheritDoc
     */
    #[Override]
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
     * @inheritDoc
     */
    #[Override]
    public function removeAllFlashes(): void
    {
        $counters = $this->getFlashCounters();
        foreach (array_keys($counters) as $key) {
            $this->remove($key);
        }

        $this->remove(SessionBlockInterface::FLASH_KEY);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasFlash($key): bool
    {
        return $this->getFlash($key) !== null;
    }

    /**
     * Reads the flash counter map from the session, normalizing it to a
     * map of string flash keys to their integer counters. Non-conforming
     * entries that may exist in raw session data are discarded.
     *
     * @return array<string, int>
     */
    protected function getFlashCounters(): array
    {
        $raw = $this->get(SessionBlockInterface::FLASH_KEY, []);
        if (!\is_array($raw)) {
            return [];
        }

        $counters = [];
        foreach ($raw as $key => $count) {
            if (\is_int($count)) {
                $counters[(string) $key] = $count;
            }
        }

        return $counters;
    }

    /**
     * Loads the segment only if the session has already been started, or if
     * a session is available (in which case it resumes the session first).
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
     */
    protected function load(): void
    {
        $_SESSION[$this->name] ??= [];
        $_SESSION[SessionBlockInterface::FLASH_KEY] ??= [];
    }

    /**
     * Resumes a previous session, or starts a new one, and loads the segment.
     */
    protected function resumeOrStartSession(): void
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
    protected function updateFlashCounters(): void
    {
        $counters = $this->get(SessionBlockInterface::FLASH_KEY, []);
        if (\is_array($counters)) {
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
