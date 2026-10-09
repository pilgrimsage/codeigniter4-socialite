<?php

namespace Fluent\Socialite\Helpers;

use function base64_encode;
use function random_bytes;
use function str_replace;
use function strlen;
use function strncmp;
use function substr;
use function ucwords;

class Str
{
    /**
     * Generate a more truly "random" alpha-numeric string.
     *
     * @param  int  $length
     * @return string
     */
    public static function random($length = 16)
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;

            $bytes = random_bytes($size);

            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    public static function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a value to StudlyCase (e.g. "linkedin-openid" becomes "LinkedinOpenid").
     *
     * @param  string  $value
     * @return string
     */
    public static function studly($value)
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', (string) $value)));
    }
}
