<?php
namespace Altair\Security\Support;

class Salt
{
    /**
     * @param int $length
     *
     * @return string
     */
    public function generate(int $length = 32): string
    {
        return substr(strtr(base64_encode(random_bytes($length)), '+/=', '_-.'), 0, $length);
    }
}
