<?php
namespace Validation\Rule;

use Altair\Validation\Rule\AbstractRule;

class UrlRule extends AbstractRule
{
    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        // check whether there is any invalid char in the URL
        if (preg_match('/[^a-zA-Z0-9$-_.+!*\'(),{}|\^~\[\]`<>#%";\/?:@&=]/', $value)) {
            return false;
        }

        // now make sure it parses as a URL with scheme and host
        $result = @parse_url($value);

        $scheme = trim($result['scheme']);
        $host = trim($result['host']);

        return !(empty($scheme) || empty($host));
    }
    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid URL.', $value);
    }
}
