<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

interface HttpStatusInterface
{
    public const HTTP_CONTINUE = 'Continue';

    public const HTTP_SWITCHING_PROTOCOLS = 'Switching Protocols';

    public const HTTP_PROCESSING = 'Processing';

    public const HTTP_OK = 'OK';

    public const HTTP_CREATED = 'Created';

    public const HTTP_ACCEPTED = 'Accepted';

    public const HTTP_NON_AUTHORITATIVE_INFORMATION = 'Non-Authoritative Information';

    public const HTTP_NO_CONTENT = 'No Content';

    public const HTTP_RESET_CONTENT = 'Reset Content';

    public const HTTP_PARTIAL_CONTENT = 'Partial Content';

    public const HTTP_MULTI_STATUS = 'Multi-Status';

    public const HTTP_ALREADY_REPORTED = 'Already Reported';

    public const HTTP_IM_USED = 'IM Used';

    public const HTTP_MULTIPLE_CHOICES = 'Multiple Choices';

    public const HTTP_MOVED_PERMANENTLY = 'Moved Permanently';

    public const HTTP_FOUND = 'Found';

    public const HTTP_SEE_OTHER = 'See Other';

    public const HTTP_NOT_MODIFIED = 'Not Modified';

    public const HTTP_USE_PROXY = 'Use Proxy';

    public const HTTP_TEMPORARY_REDIRECT = 'Temporary Redirect';

    public const HTTP_PERMANENT_REDIRECT = 'Permanent Redirect';

    public const HTTP_BAD_REQUEST = 'Bad Request';

    public const HTTP_UNAUTHORIZED = 'Unauthorized';

    public const HTTP_PAYMENT_REQUIRED = 'Payment Required';

    public const HTTP_FORBIDDEN = 'Forbidden';

    public const HTTP_NOT_FOUND = 'Not Found';

    public const HTTP_METHOD_NOT_ALLOWED = 'Method Not Allowed';

    public const HTTP_NOT_ACCEPTABLE = 'Not Acceptable';

    public const HTTP_PROXY_AUTHENTICATION_REQUIRED = 'Proxy Authentication Required';

    public const HTTP_REQUEST_TIMEOUT = 'Request Timeout';

    public const HTTP_CONFLICT = 'Conflict';

    public const HTTP_GONE = 'Gone';

    public const HTTP_LENGTH_REQUIRED = 'Length Required';

    public const HTTP_PRECONDITION_FAILED = 'Precondition Failed';

    public const HTTP_PAYLOAD_TOO_LARGE = 'Payload Too Large';

    public const HTTP_URI_TOO_LONG = 'URI Too Long';

    public const HTTP_UNSUPPORTED_MEDIA_TYPE = 'Unsupported Media Type';

    public const HTTP_RANGE_NOT_SATISFIABLE = 'Range Not Satisfiable';

    public const HTTP_EXPECTATION_FAILED = 'Expectation Failed';

    public const HTTP_IM_A_TEAPOT = "I'm a teapot";

    public const HTTP_MISDIRECTED_REQUEST = 'Misdirected Request';

    public const HTTP_UNPROCESSABLE_ENTITY = 'Unprocessable Entity';

    public const HTTP_LOCKED = 'Locked';

    public const HTTP_FAILED_DEPENDENCY = 'Failed Dependency';

    public const HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL = 'Reserved for WebDAV advanced collections expired proposal';

    public const HTTP_UPGRADE_REQUIRED = 'Upgrade Required';

    public const HTTP_PRECONDITION_REQUIRED = 'Precondition Required';

    public const HTTP_TOO_MANY_REQUESTS = 'Too Many Request';

    public const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 'Request Header Fields Too Large';

    public const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 'Unavailable For Legal Reasons';

    public const HTTP_INTERNAL_SERVER_ERROR = 'Internal Server Error';

    public const HTTP_NOT_IMPLEMENTED = 'Not Implemented';

    public const HTTP_BAD_GATEWAY = 'Bad Gateway';

    public const HTTP_SERVICE_UNAVAILABLE = 'Service Unavailable';

    public const HTTP_GATEWAY_TIMEOUT = 'Gateway Timeout';

    public const HTTP_VERSION_NOT_SUPPORTED = 'HTTP Version Not Supported';

    public const HTTP_VARIANT_ALSO_NEGOTIATES = 'Variant Also Negotiates';

    public const HTTP_INSUFFICIENT_STORAGE = 'Insufficient Storage';

    public const HTTP_LOOP_DETECTED = 'Loop Detected';

    public const HTTP_NOT_EXTENDED = 'Not Extended';

    public const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 'Network Authentication Required';
}
