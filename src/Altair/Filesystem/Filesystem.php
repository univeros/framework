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
     * @param  string $path
     * @param  bool $lock
     *
     * @throws FileNotFoundException
     *
     * @return string
     *
     */
    public function get(string $path, bool $lock = false): string
    {
        if ($this->isFile($path)) {
            return $lock ? $this->getShared($path) : file_get_contents($path);
        }
        throw new FileNotFoundException("File does not exist at path {$path}");
    }

    /**
     * Get contents of a file with shared access.
     *
     * @param  string $path
     *
     * @return string
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
     * @param string $path
     *
     * @return array
     */
    public function readLines(string $path): array
    {
        // Read file into an array of lines with auto-detected line endings
        $autodetect = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', '1');
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        ini_set('auto_detect_line_endings', $autodetect);

        return $lines;
    }

    /**
     * Get the returned value of a file.
     *
     * @param  string $path
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
        throw new FileNotFoundException("File does not exist at path {$path}");
    }

    /**
     * Require the given file once.
     *
     * @param  string $file
     *
     * @return void
     */
    public function requireOnce(string $file)
    {
        require_once $file;
    }

    /**
     * Gets or sets UNIX mode of a file or directory.
     *
     * @param  string $path
     * @param  int $mode
     *
     * @return mixed
     */
    public function chmod($path, $mode = null)
    {
        if ($mode) {
            return chmod($path, $mode);
        }

        return substr(sprintf('%o', fileperms($path)), -4);
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param  string $path
     *
     * @return bool
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
     *
     * @return bool
     */
    public function move($path, $target): bool
    {
        return rename($path, $target);
    }

    /**
     * Write the contents of a file.
     *
     * @param  string $path
     * @param  string $contents
     * @param  bool $lock
     *
     * @return int|false
     */
    public function put(string $path, string $contents, bool $lock = false)
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Prepend to a file.
     *
     * @param  string $path
     * @param  string $data
     *
     * @return int|false
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
     * @param  string $path
     * @param  string $data
     *
     * @return int|bool
     */
    public function append(string $path, string $data)
    {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    /**
     * Delete the file at a given path.
     *
     * @param  string|array $paths
     *
     * @return bool
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
            } catch (ErrorException $e) {
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
     *
     * @return bool
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
     *
     * @return bool
     */
    public function link($target, $link): bool
    {
        if (strtolower(substr(PHP_OS, 0, 3)) !== 'win') {
            return symlink($target, $link);
        }
        $mode = $this->isDirectory($target) ? 'J' : 'H';
        exec("mklink /{$mode} \"{$link}\" \"{$target}\"");
        return true;
    }

    /**
     * Create a directory.
     *
     * @param  string $path
     * @param  int $mode
     * @param  bool $recursive
     * @param  bool $force
     *
     * @return bool
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Move a directory.
     *
     * @param  string $from
     * @param  string $to
     * @param  bool $overwrite
     *
     * @return bool
     */
    public function moveDirectory($from, $to, $overwrite = false): bool
    {
        if ($overwrite && $this->isDirectory($to)) {
            if (!$this->deleteDirectory($to)) {
                return false;
            }
        }

        return @rename($from, $to) === true;
    }

    /**
     * Copy a directory from one location to another.
     *
     * @param  string $directory
     * @param  string $destination
     * @param  int $options
     *
     * @return bool
     */
    public function copyDirectory($directory, $destination, $options = null): bool
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
            }
            // If the current items is just a regular file, we will just copy this to the new
            // location and keep looping. If for some reason the copy fails we'll bail out
            // and return false, so the developer is aware that the copy process failed.
            else {
                if (!$this->copy($item->getPathname(), $target)) {
                    return false;
                }
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
     *
     * @return bool
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
     * @param string $path
     *
     * @throws InvalidArgumentException
     * @return bool
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
     * @param  string $path
     *
     * @return string
     */
    public function getFileName(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Get the MD5 hash of the file at the given path.
     *
     * @param  string $path
     *
     * @return string
     */
    public function getFileHash($path): string
    {
        return md5_file($path);
    }

    /**
     * Extract the trailing name component from a file path.
     *
     * @param  string $path
     *
     * @return string
     */
    public function getFileBasename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * Extract the file extension from a file path.
     *
     * @param  string $path
     *
     * @return string
     */
    public function getFileExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Get the type of a given path. Possible values are fifo, char, dir, block, link, file, socket and unknown.
     *
     * @param  string $path
     *
     * @return string
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
     * @param  string $path
     *
     * @return string|false
     */
    public function getFileMimeType(string $path)
    {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    }

    /**
     * Get the file size of a given file.
     *
     * @param  string $path
     *
     * @return int
     */
    public function getFileSize(string $path)
    {
        return filesize($path);
    }

    /**
     * Extract the parent directory from a file path.
     *
     * @param  string $path
     *
     * @return string
     */
    public function getDirectoryName(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /**
     * Get the file's last modification time.
     *
     * @param  string $path
     *
     * @return int|false
     */
    public function getLastModified(string $path)
    {
        return filemtime($path);
    }

    /**
     * Determine if the given path is a directory.
     *
     * @param  string $directory
     *
     * @return bool
     */
    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Determine if the given path is readable.
     *
     * @param  string $path
     *
     * @return bool
     */
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * Determine if the given path is writable.
     *
     * @param  string $path
     *
     * @return bool
     */
    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Determine if the given path is a file.
     *
     * @param  string $file
     *
     * @return bool
     */
    public function isFile(string $file): bool
    {
        return is_file($file);
    }

    /**
     * Find path names matching a given pattern.
     *
     * @param  string $pattern
     * @param  int $flags
     *
     * @return array|false
     */
    public function glob(string $pattern, int $flags = 0)
    {
        return glob($pattern, $flags);
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param  string $directory
     *
     * @return array
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
            function ($file) {
                return filetype($file) === 'file';
            }
        );
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param string $directory
     * @param string $pattern
     * @param boolean $ignoreDotFiles
     *
     * @return \SplFileInfo[]
     */
    public function listAllFiles($directory, $pattern = '/^.*\.*$/i', $ignoreDotFiles = true): array
    {
        if (!$this->isDirectory($directory)) {
            throw new InvalidArgumentException("The directory argument must be a directory: $directory");
        }
        $dirIterator = new RecursiveDirectoryIterator($directory);
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $files = [];
        foreach ($iterator as $file) {
            if ($ignoreDotFiles && $file->getBasename()[0] === '.') {
                continue;
            }

            if ($file->isFile() && preg_match($pattern, $file->getFilename())) {
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
     *
     * @return array
     */
    public function listDirectories($directory, $ignoreDotDirectories = true): array
    {
        if (!$this->isDirectory($directory)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a directory.', $directory));
        }
        $directories = [];
        foreach ((new DirectoryIterator($directory)) as $file) {
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
