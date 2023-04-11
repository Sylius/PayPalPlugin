<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin;

/**
 * Code copied from https://github.com/Nyholm/append_query_string.
 */
final class UrlUtils
{
    public const APPEND_QUERY_STRING_IGNORE_DUPLICATE = 0;

    public const APPEND_QUERY_STRING_REPLACE_DUPLICATE = 1;

    public const APPEND_QUERY_STRING_SKIP_DUPLICATE = 2;

    private function __construct()
    {
    }

    /**
     * Add a query string to an existing URL.
     *
     * @param string $url         The base URL. Example "https://nyholm.tech?biz=1"
     * @param string $queryString A string like "foo=bar&baz=2"
     * @param int    $mode        How to handle duplicate keys. See predefined constants above.
     *
     * @return string the resulting string
     */
    public static function appendQueryString(string $url, string $queryString, int $mode = self::APPEND_QUERY_STRING_IGNORE_DUPLICATE): string
    {
        if ('' === $queryString) {
            return $url;
        }

        $existing = parse_url($url, \PHP_URL_QUERY);
        $fragment = parse_url($url, \PHP_URL_FRAGMENT);
        $fragment = $fragment ? '#' . $fragment : '';

        // Remove fragment
        if (false !== strrpos($url, '#')) {
            $url = substr($url, 0, strrpos($url, '#'));
        }

        // If no existing query string
        if (empty($existing)) {
            // Check for "?" at the last character in $url
            $questionMark = '?';
            if ('?' === $url[strlen($url) - 1]) {
                $questionMark = '';
            }

            return $url . $questionMark . $queryString . $fragment;
        }

        // Remove query string from URL
        $result = substr($url, 0, strrpos($url, $existing) ?: 0);

        if (self::APPEND_QUERY_STRING_IGNORE_DUPLICATE === $mode) {
            $result .= $existing . '&' . $queryString;
        } else {
            preg_match_all('#([^&=]+)(=[^&]+)?#si', $existing, $existingArray);
            preg_match_all('#([^&=]+)(=[^&]+)?#si', $queryString, $queryStringArray);
            if (self::APPEND_QUERY_STRING_REPLACE_DUPLICATE === $mode) {
                $intersect = array_intersect($existingArray[1], $queryStringArray[1]);
                $keyMap = array_flip($queryStringArray[1]);
                foreach ($intersect as $key => $paramName) {
                    $existing = str_replace($existingArray[0][$key], $queryStringArray[0][$keyMap[$paramName]], $existing);
                    $queryString = str_replace($queryStringArray[0][$keyMap[$paramName]], '', $queryString);
                }
            } elseif (self::APPEND_QUERY_STRING_SKIP_DUPLICATE === $mode) {
                $intersect = array_intersect($queryStringArray[1], $existingArray[1]);
                foreach ($intersect as $key => $paramName) {
                    $queryString = str_replace($queryStringArray[0][$key], '', $queryString);
                }
            }
            $result .= trim((string) preg_replace('#&&+#i', '&', $existing . '&' . $queryString), '&');
        }

        // add fragment
        return $result . $fragment;
    }
}
