<?php
namespace Altair\Http\Support;


use Altair\Http\Contracts\FormatNegotiatorInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Exception\InvalidArgumentException;
use Exception;
use Negotiation\Negotiator;
use Psr\Http\Message\ServerRequestInterface;

class FormatNegotiator implements FormatNegotiatorInterface
{
    /**
     * @var array Available formats with the mime types
     * - Thanks oscarotero/psr7-middlewares
     */
    protected $formats = [
        //text
        'html' => [['html', 'htm', 'php'], ['text/html', 'application/xhtml+xml']],
        'txt' => [['txt'], ['text/plain']],
        'css' => [['css'], ['text/css']],
        'json' => [['json'], ['application/json', 'text/json', 'application/x-json']],
        'jsonp' => [['jsonp'], ['text/javascript', 'application/javascript', 'application/x-javascript']],
        'js' => [['js'], ['text/javascript', 'application/javascript', 'application/x-javascript']],
        //xml
        'rdf' => [['rdf'], ['application/rdf+xml']],
        'rss' => [['rss'], ['application/rss+xml']],
        'atom' => [['atom'], ['application/atom+xml']],
        'xml' => [['xml'], ['text/xml', 'application/xml', 'application/x-xml']],
        //images
        'bmp' => [['bmp'], ['image/bmp']],
        'gif' => [['gif'], ['image/gif']],
        'png' => [['png'], ['image/png', 'image/x-png']],
        'jpg' => [['jpg', 'jpeg', 'jpe'], ['image/jpeg', 'image/jpg']],
        'svg' => [['svg', 'svgz'], ['image/svg+xml']],
        'psd' => [['psd'], ['image/vnd.adobe.photoshop']],
        'eps' => [['ai', 'eps', 'ps'], ['application/postscript']],
        'ico' => [['ico'], ['image/x-icon', 'image/vnd.microsoft.icon']],
        //audio/video
        'mov' => [['mov', 'qt'], ['video/quicktime']],
        'mp3' => [['mp3'], ['audio/mpeg']],
        'mp4' => [['mp4'], ['video/mp4']],
        'ogg' => [['ogg'], ['audio/ogg']],
        'ogv' => [['ogv'], ['video/ogg']],
        'webm' => [['webm'], ['video/webm']],
        'webp' => [['webp'], ['image/webp']],
        //fonts
        'eot' => [['eot'], ['application/vnd.ms-fontobject']],
        'otf' => [['otf'], ['font/opentype', 'application/x-font-opentype']],
        'ttf' => [['ttf'], ['font/ttf', 'application/font-ttf', 'application/x-font-ttf']],
        'woff' => [['woff'], ['font/woff', 'application/font-woff', 'application/x-font-woff']],
        'woff2' => [['woff2'], ['font/woff2', 'application/font-woff2', 'application/x-font-woff2']],
        //other formats
        'pdf' => [['pdf'], ['application/pdf', 'application/x-download']],
        'zip' => [['zip'], ['application/zip', 'application/x-zip', 'application/x-zip-compressed']],
        'rar' => [['rar'], ['application/rar', 'application/x-rar', 'application/x-rar-compressed']],
        'exe' => [['exe'], ['application/x-msdownload']],
        'msi' => [['msi'], ['application/x-msdownload']],
        'cab' => [['cab'], ['application/vnd.ms-cab-compressed']],
        'doc' => [['doc'], ['application/msword']],
        'rtf' => [['rtf'], ['application/rtf']],
        'xls' => [['xls'], ['application/vnd.ms-excel']],
        'ppt' => [['ppt'], ['application/vnd.ms-powerpoint']],
        'odt' => [['odt'], ['application/vnd.oasis.opendocument.text']],
        'ods' => [['ods'], ['application/vnd.oasis.opendocument.spreadsheet']],
    ];

    /**
     * @inheritdoc
     */
    public function getFromServerRequestAttribute(ServerRequestInterface $request): ?string
    {
        return $request->getAttribute(MiddlewareInterface::ATTRIBUTE_FORMAT);
    }

    /**
     * @inheritdoc
     */
    public function getFromServerRequestUriPath(ServerRequestInterface $request): ?string
    {
        $extension = strtolower(pathinfo($request->getUri()->getPath(), PATHINFO_EXTENSION));

        if (!empty($extension)) {
            foreach ($this->formats as $format => $data) {
                if (in_array($extension, $data[0], true)) {
                    return $format;
                }
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getFromServerRequestHeaderLine(ServerRequestInterface $request): ?string
    {
        $headers = call_user_func('array_merge', array_column($this->formats, 1));
        $mimeType = $this->negotiateHeader($request->getHeaderLine('Accept'), $headers);

        if (null !== $mimeType) {
            foreach ($this->formats as $format => $data) {
                if (in_array($mimeType, $data[1], true)) {
                    return $format;
                }
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getContentTypeByFormat(string $format): string
    {
        if (isset($this->formats[$format])) {
            throw new InvalidArgumentException(sprintf('Unknown format "%s"', $format));
        }

        return $this->formats[$format][1][0];
    }

    /**
     * Returns the best format value for server request header.
     *
     * @param string $accept
     * @param array $priorities
     *
     * @return null|string
     */
    protected function negotiateHeader(string $accept, array $priorities): ?string
    {
        if (empty($accept) || empty($priorities)) {
            return null;
        }

        try {
            $best = (new Negotiator())->getBest($accept, $priorities);
        } catch (Exception $e) {
            return null;
        }

        if (null !== $best) {
            return $best->getValue();
        }

        return null;
    }
}
