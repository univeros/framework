<?php
namespace Altair\Filesystem;

use Altair\Filesystem\Exception\FileNotFoundException;
use Altair\Filesystem\Exception\InvalidArgumentException;
use Altair\Filesystem\Exception\UnreadableFileException;
use DirectoryIterator;
use ErrorException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

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
    public function get(string $path, bool $lock = false)
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
                    $contents = fread($handle, $this->size($path) ?: 1);
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
     * @return mixed
     */
    public function requireOnce(string $file)
    {
        require_once $file;
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param  string $path
     *
     * @return bool
     */
    public function exists($path)
    {
        return file_exists($path);
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
     * @return int
     */
    public function append($path, $data)
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
    public function deleteFile($paths): bool
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
     * Clears the directory by deleting its contents recursively.
     *
     * @param string $path
     *
     * @return bool
     * @throws UnreadableFileException
     */
    public function clearDirectory(string $path): bool
    {
        if (!$this->isDirectory($path)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a directory.', $path));
        }
        $contents = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS, RecursiveIteratorIterator::SELF_FIRST)
        );

        /** @var \SplFileInfo $file */
        foreach ($contents as $file) {
            if (!$file->isReadable()) {
                throw new UnreadableFileException(
                    sprintf(
                        'Unreadable file encountered: "%s"',
                        $file->getRealPath()
                    )
                );
            }
            switch ($file->getType()) {
                case 'dir':
                    rmdir($file->getRealPath());
                    break;
                case 'link':
                    unlink($file->getPathname());
                    break;
                default:
                    unlink($file->getRealPath());
            }

        }

        return true;
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
     * Get the file type of a given file.
     *
     * @param  string $path
     *
     * @return string
     */
    public function getFileType(string $path): string
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
     * @return array
     */
    public function listAllFiles($directory, $pattern = '/^.*\.*$/i', $ignoreDotFiles = true)
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
                $name = $this->getFileName($file->getBasename());
                $files[$name] = $file->getPathname();
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
    public function listDirectories($directory, $ignoreDotDirectories = true)
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
