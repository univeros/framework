<?php
namespace Altair\Http\Support;

use Altair\Http\Contracts\ErrorHandlerInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;

class DefaultErrorHandler implements ErrorHandlerInterface
{
    protected $handlers = [
        'plain' => [
            'text/plain',
            'text/css',
            'text/javascript',
        ],
        'jpeg' => [
            'image/jpeg',
        ],
        'gif' => [
            'image/gif',
        ],
        'png' => [
            'image/png',
        ],
        'svg' => [
            'image/svg+xml',
        ],
        'json' => [
            'application/json',
        ],
        'xml' => [
            'text/xml',
        ],
    ];

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $error = $request->getAttribute(MiddlewareInterface::ATTRIBUTE_EXCEPTION);
        $accept = (new MimeType())->getFromResponseHeaderLine($response);
        $message = $error !== null ? $error->getMessage() : '';
        $response = (new Response('php://memory', $response->getStatusCode()));

        foreach ($this->handlers as $method => $types) {
            foreach ($types as $type) {
                if (stripos($accept, $type) !== false) {
                    call_user_func([$this, $type], $response->getStatusCode(), $message);

                    return $response->withHeader('Content-Type', $type);
                }
            }
        }
        $this->html($response->getStatusCode(), $message);

        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Output the error as svg image.
     *
     * @param int $statusCode
     * @param string $message
     */
    protected function svg($statusCode, $message)
    {
        echo <<<EOT
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="50" viewBox="0 0 200 50">
    <text x="20" y="30" font-family="sans-serif" title="{$message}">
        Error {$statusCode}
    </text>
</svg>
EOT;
    }

    /**
     * Output the error as html.
     *
     * @param int $statusCode
     * @param string $message
     */
    protected function html($statusCode, $message)
    {
        echo <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Error {$statusCode}</title>
    <style>html{font-family: sans-serif;}</style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h1>Error {$statusCode}</h1>
    {$message}
</body>
</html>
EOT;
    }

    /**
     * Output the error as json.
     *
     * @param int $statusCode
     * @param string $message
     */
    protected function json($statusCode, $message)
    {
        $output = ['error' => $statusCode];
        if (!empty($message)) {
            $output['message'] = $message;
        }
        echo json_encode($output);
    }

    /**
     * Output the error as xml.
     *
     * @param int $statusCode
     * @param string $message
     */
    protected function xml($statusCode, $message)
    {
        echo <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<error>
    <code>{$statusCode}</code>
    <message>{$message}</message>
</error>
EOT;
    }

    /**
     * Output the error as jpeg.
     *
     * @param int $statusCode
     * @param string $message
     */
    protected function jpeg($statusCode, $message)
    {
        $image = $this->createImage($statusCode, $message);
        imagejpeg($image);
    }

    /**
     * Output the error as gif.
     *
     * @param int $statusCode
     * @param string $message
     */
    protected function gif($statusCode, $message)
    {
        $image = $this->createImage($statusCode, $message);
        imagegif($image);
    }

    /**
     * Output the error as png.
     *
     * @param int $statusCode
     * @param string $message
     */
    protected function png($statusCode, $message)
    {
        $image = $this->createImage($statusCode, $message);
        imagepng($image);
    }

    /**
     * Creates a image resource with the error text.
     *
     * @param int $statusCode
     * @param string $message
     *
     * @return resource
     */
    protected function createImage($statusCode, $message)
    {
        $size = 200;
        $image = imagecreatetruecolor($size, $size);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 10, 10, "Error {$statusCode}", $textColor);
        foreach (str_split($message, intval($size / 10)) as $line => $text) {
            imagestring($image, 5, 10, ($line * 18) + 28, $text, $textColor);
        }

        return $image;
    }
}
