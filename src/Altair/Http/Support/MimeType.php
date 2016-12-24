<?php
namespace Altair\Http\Support;

use Psr\Http\Message\ResponseInterface;
use SplFileInfo;

class MimeType
{
    /**
     * default mime type
     */
    const OCTET_STREAM_MIME_TYPE = 'application/octet-stream';

    /**
     * @var array
     */
    protected $mimeTypes = [
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',
        // Images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        // Archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',
        // Audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
        // Adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',
        // MS Office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        // Open Office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];

    /**
     * @param ResponseInterface $response
     * @return string
     */
    public function getFromResponse(ResponseInterface $response): string
    {
        $mime = strtolower($response->getHeaderLine('Content-Type'));
        $mime = explode(';', $mime, 2);
        return trim($mime[0]);
    }

    /**
     * @param string $file
     * @return string
     */
    public function getFromFile(string $file): string
    {
        $file = new SplFileInfo($file);

        $extension = strtolower($file->getExtension());
        if ($extension === '') {
            return static::OCTET_STREAM_MIME_TYPE;
        }
        if (array_key_exists($extension, $this->mimeTypes)) {
            return $this->mimeTypes[$extension];
        }
        if (function_exists('finfo_open') && $file->isFile()) {
            $path = $file->getPath();
            $fileInfo = finfo_open(FILEINFO_MIME);
            $mimeType = finfo_file($fileInfo, $path);
            finfo_close($fileInfo);
            return $mimeType;
        }
        return static::OCTET_STREAM_MIME_TYPE;
    }
}