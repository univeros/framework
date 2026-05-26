<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Filesystem;

use Altair\Filesystem\Exception\FileNotFoundException;
use Altair\Filesystem\Exception\InvalidArgumentException;
use DirectoryIterator;
use ErrorException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Thanks Laravel
 */
class Filesystem
{
    /**
     * Get the contents of a file.
     *
     *
     * @throws FileNotFoundException
     *
     *
     */
    public function get(string $path, bool $lock = false): string
    {
        if ($this->isFile($path)) {
            return $lock ? $this->getShared($path) : file_get_contents($path);
        }

        throw new FileNotFoundException('File does not exist at path ' . $path);
    }

    /**
     * Get contents of a file with shared access.
     *
     *
     */
    public function getShared(string $path): string
    {
        $contents = '';
        $handle = fopen($path, 'rb');
        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);
                    $contents = fread($handle, $this->getFileSize($path) ?: 1);
                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $contents;
    }

    /**
     * Read the contents of a file as an array of lines.
     *
     *
     */
    public function readLines(string $path): array
    {
        // auto_detect_line_endings was deprecated in PHP 8.1; PHP now handles CRLF/CR
        // line endings natively for file()/fgets() without configuration.
        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    /**
     * Get the returned value of a file.
     *
     *
     * @throws FileNotFoundException
     *
     * @return mixed
     *
     */
    public function getRequiredFileValue(string $path)
    {
        if ($this->isFile($path)) {
            return require $path;
        }

        throw new FileNotFoundException('File does not exist at path ' . $path);
    }

    /**
     * Require the given file once.
     *
     *
     */
    public function requireOnce(string $file): void
    {
        require_once $file;
    }

    /**
     * Gets or sets UNIX mode of a file or directory.
     *
     * @param  string $path
     * @param  int $mode
     */
    public function chmod($path, $mode = null): bool|string
    {
        if ($mode) {
            return chmod($path, $mode);
        }

        return substr(sprintf('%o', fileperms($path)), -4);
    }

    /**
     * Determine if a file or directory exists.
     *
     *
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Move a file to a new location.
     *
     * @param  string $path
     * @param  string $target
     */
    public function move($path, $target): bool
    {
        return rename($path, $target);
    }

    /**
     * Write the contents of a file.
     *
     *
     * @return int|false
     */
    public function put(string $path, string $contents, bool $lock = false): int|false
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Prepend to a file.
     *
     * @throws FileNotFoundException
     * @return false|int
     */
    public function prepend(string $path, string $data)
    {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }

        return $this->put($path, $data);
    }

    /**
     * Append to a file.
     *
     *
     */
    public function append(string $path, string $data): int|false
    {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    /**
     * Delete the file at a given path.
     *
     * @param  string|array $paths
     */
    public function delete($paths): bool
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        $success = true;
        foreach ($paths as $path) {
            try {
                if (!@unlink($path)) {
                    $success = false;
                }
            } catch (ErrorException) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Copy a file to a new location.
     *
     * @param  string $path
     * @param  string $target
     */
    public function copy($path, $target): bool
    {
        return copy($path, $target);
    }

    /**
     * Create a hard link to the target file or directory.
     *
     * @param  string $target
     * @param  string $link
     */
    public function link($target, $link): bool
    {
        if (stripos(PHP_OS, 'win') !== 0) {
            return symlink($target, $link);
        }

        $mode = $this->isDirectory($target) ? 'J' : 'H';
        exec(sprintf('mklink /%s "%s" "%s"', $mode, $link, $target));
        return true;
    }

    /**
     * Create a directory.
     *
     *
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if (file_exists($path) && is_dir($path)) {
            return true;
        }

        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Move a directory.
     *
     * @param string $from
     * @param string $to
     * @param bool $overwrite
     */
    public function moveDirectory($from, $to, $overwrite = false): bool
    {
        if ($overwrite && $this->isDirectory($to) && !$this->deleteDirectory($to)) {
            return false;
        }

        return @rename($from, $to);
    }

    /**
     * Copy a directory from one location to another.
     *
     * @param  string $directory
     * @param  int $options
     *
     */
    public function copyDirectory($directory, string $destination, $options = null): bool
    {
        if (!$this->isDirectory($directory)) {
            return false;
        }

        $options = $options ?: FilesystemIterator::SKIP_DOTS;
        // If the destination directory does not actually exist, we will go ahead and
        // create it recursively, which just gets the destination prepared to copy
        // the files over. Once we make the directory we'll proceed the copying.
        if (!$this->isDirectory($destination)) {
            $this->makeDirectory($destination, 0777, true);
        }

        $items = new FilesystemIterator($directory, $options);
        foreach ($items as $item) {
            // As we spin through items, we will check to see if the current file is actually
            // a directory or a file. When it is actually a directory we will need to call
            // back into this function recursively to keep copying these nested folders.
            $target = $destination . '/' . $item->getBasename();
            if ($item->isDir()) {
                $path = $item->getPathname();
                if (!$this->copyDirectory($path, $target, $options)) {
                    return false;
                }
            } elseif (!$this->copy($item->getPathname(), $target)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recursively delete a directory.
     *
     * The directory itself may be optionally preserved.
     *
     * @param  string $directory
     * @param  bool $preserve
     */
    public function deleteDirectory($directory, $preserve = false): bool
    {
        if (!$this->isDirectory($directory)) {
            return false;
        }

        $items = new FilesystemIterator($directory);
        foreach ($items as $item) {
            // If the item is a directory, we can just recurse into the function and
            // delete that sub-directory otherwise we'll just delete the file and
            // keep iterating through each file until the directory is cleaned.
            if ($item->isDir() && !$item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            }
            // If the item is just a file, we can go ahead and delete it since we're
            // just looping through and waxing all of the files in this directory
            // and calling directories recursively, so we delete the real path.
            else {
                $this->delete($item->getPathname());
            }
        }

        if (!$preserve) {
            @rmdir($directory);
        }

        return true;
    }

    /**
     * Clears the directory by deleting its contents recursively.
     *
     *
     * @throws InvalidArgumentException
     */
    public function clearDirectory(string $path): bool
    {
        if (!$this->isDirectory($path)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a directory.', $path));
        }

        return $this->deleteDirectory($path, true);
    }

    /**
     * Extract the file name from a file path.
     *
     *
     */
    public function getFileName(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Get the MD5 hash of the file at the given path.
     *
     * @param  string $path
     */
    public function getFileHash($path): string
    {
        return md5_file($path);
    }

    /**
     * Extract the trailing name component from a file path.
     *
     *
     */
    public function getFileBasename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * Extract the file extension from a file path.
     *
     *
     */
    public function getFileExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Get the type of a given path. Possible values are fifo, char, dir, block, link, file, socket and unknown.
     *
     *
     *
     * @see http://php.net/manual/en/function.filetype.php
     */
    public function getType(string $path): string
    {
        return filetype($path);
    }

    /**
     * Get the mime-type of a given file.
     *
     *
     * @return string|false
     */
    public function getFileMimeType(string $path): string|false
    {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    }

    /**
     * Get the file size of a given file.
     *
     *
     * @return int
     */
    public function getFileSize(string $path): int|false
    {
        return filesize($path);
    }

    /**
     * Extract the parent directory from a file path.
     *
     *
     */
    public function getDirectoryName(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /**
     * Get the file's last modification time.
     *
     *
     * @return int|false
     */
    public function getLastModified(string $path): int|false
    {
        return filemtime($path);
    }

    /**
     * Determine if the given path is a directory.
     *
     *
     */
    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Determine if the given path is readable.
     *
     *
     */
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * Determine if the given path is writable.
     *
     *
     */
    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Determine if the given path is a file.
     *
     *
     */
    public function isFile(string $file): bool
    {
        return is_file($file);
    }

    /**
     * Find path names matching a given pattern.
     *
     *
     * @return array|false
     */
    public function glob(string $pattern, int $flags = 0): array|false
    {
        return glob($pattern, $flags);
    }

    /**
     * Get an array of all files in a directory.
     *
     *
     */
    public function listFiles(string $directory): array
    {
        $glob = glob($directory . '/*');
        if ($glob === false) {
            return [];
        }

        // To get the appropriate files, we'll simply glob the directory and filter
        // out any "files" that are not truly files so we do not end up with any
        // directories in our list, but only true files within the directory.
        return array_filter(
            $glob,
            fn($file): bool => filetype($file) === 'file'
        );
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param string $pattern
     * @param boolean $ignoreDotFiles
     * @return \SplFileInfo[]
     */
    public function listAllFiles(string $directory, $pattern = '/^.*\.*$/i', $ignoreDotFiles = true): array
    {
        if (!$this->isDirectory($directory)) {
            throw new InvalidArgumentException('The directory argument must be a directory: ' . $directory);
        }

        $dirIterator = new RecursiveDirectoryIterator($directory);
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $files = [];
        foreach ($iterator as $file) {
            if ($ignoreDotFiles && $file->getBasename()[0] === '.') {
                continue;
            }

            if ($file->isFile() && preg_match($pattern, (string) $file->getFilename())) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Get all of the directories within a given directory.
     *
     * @param string $directory
     * @param boolean $ignoreDotDirectories whether to ignore the dotted directories or not.
     */
    public function listDirectories($directory, $ignoreDotDirectories = true): array
    {
        if (!$this->isDirectory($directory)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a directory.', $directory));
        }

        $directories = [];
        foreach (new DirectoryIterator($directory) as $file) {
            if ($ignoreDotDirectories && $file->isDot()) {
                continue;
            }

            if ($file->isDir()) {
                $directories[$file->getBasename()] = $file->getPathname();
            }
        }

        return $directories;
    }
}
