<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\UrlUtils;

final class UrlUtilsTest extends TestCase
{
    public function provideBaseUrls(): iterable
    {
        yield ['https://foo.com', '', 'https://foo.com'];
        yield ['https://foo.com?', '', 'https://foo.com?'];
        yield ['https://foo.com', 'foo', 'https://foo.com?foo'];
        yield ['https://foo.com/', 'foo', 'https://foo.com/?foo'];
        yield ['https://foo.com', 'foo=bar', 'https://foo.com?foo=bar'];
        yield ['https://foo.com#aa', 'foo=bar', 'https://foo.com?foo=bar#aa'];
        yield ['https://foo.com/', 'foo=bar', 'https://foo.com/?foo=bar'];
        yield ['https://foo.com?', 'foo=bar', 'https://foo.com?foo=bar'];
        yield ['https://foo.com?', 'foo=bar%20bar', 'https://foo.com?foo=bar%20bar'];
        yield ['https://foo.com?', 'foo=bar+bar', 'https://foo.com?foo=bar+bar'];
        yield ['https://foo.com/page', 'foo=bar', 'https://foo.com/page?foo=bar'];
        yield ['https://foo.com/page/', 'foo=bar', 'https://foo.com/page/?foo=bar'];
        yield ['https://user:pass@foo.com/page/', 'foo=bar', 'https://user:pass@foo.com/page/?foo=bar'];
        yield ['https://user@foo.com/page/', 'foo=bar', 'https://user@foo.com/page/?foo=bar'];
        yield ['https://user:@foo.com/page/', 'foo=bar', 'https://user:@foo.com/page/?foo=bar'];
        yield ['https://foo.com/page/', 'foo=bar&biz=2', 'https://foo.com/page/?foo=bar&biz=2'];
    }

    public function provideModeIgnore(): iterable
    {
        yield from $this->provideBaseUrls();

        yield ['https://foo.com?a=b', 'foo=bar%20bar', 'https://foo.com?a=b&foo=bar%20bar'];
        yield ['https://foo.com?a=b', 'foo=bar+bar', 'https://foo.com?a=b&foo=bar+bar'];
        yield ['https://foo.com/page?aa=bb', 'foo=bar&biz=2', 'https://foo.com/page?aa=bb&foo=bar&biz=2'];
        yield ['https://foo.com/page?aa=bb#link', 'foo=bar&biz=2', 'https://foo.com/page?aa=bb&foo=bar&biz=2#link'];
        yield ['https://foo.com/page?aa=bb', 'foo=bar&aa=2', 'https://foo.com/page?aa=bb&foo=bar&aa=2'];
        yield ['https://foo.com/page?aa=b+b', 'foo=bar&aa=2', 'https://foo.com/page?aa=b+b&foo=bar&aa=2'];
        yield ['https://foo.com/page?aa=b%20b', 'foo=bar&aa=2', 'https://foo.com/page?aa=b%20b&foo=bar&aa=2'];
    }

    public function provideModeReplace(): iterable
    {
        yield from $this->provideBaseUrls();

        yield ['https://foo.com?a=b', 'foo=bar%20bar', 'https://foo.com?a=b&foo=bar%20bar'];
        yield ['https://foo.com?a=b', 'foo=bar+bar', 'https://foo.com?a=b&foo=bar+bar'];
        yield ['https://foo.com/page?aa=bb', 'foo=bar&biz=2', 'https://foo.com/page?aa=bb&foo=bar&biz=2'];
        yield ['https://foo.com/page?aa=bb#link', 'foo=bar&biz=2', 'https://foo.com/page?aa=bb&foo=bar&biz=2#link'];
        yield ['https://foo.com/page?aa=bb', 'foo=bar&aa=2', 'https://foo.com/page?aa=2&foo=bar'];
        yield ['https://foo.com/page?aa=b+b', 'foo=bar&aa=2', 'https://foo.com/page?aa=2&foo=bar'];
        yield ['https://foo.com/page?aa=b%20b', 'foo=bar&aa=2', 'https://foo.com/page?aa=2&foo=bar'];
        yield ['https://foo.com/page?aa=b+b', 'foo=bar&aa=2+2', 'https://foo.com/page?aa=2+2&foo=bar'];
        yield ['https://foo.com/page?aa=b%20b', 'foo=bar&aa=2%202', 'https://foo.com/page?aa=2%202&foo=bar'];
    }

    public function provideModeSkip(): iterable
    {
        yield from $this->provideBaseUrls();

        yield ['https://foo.com?a=b', 'foo=bar%20bar', 'https://foo.com?a=b&foo=bar%20bar'];
        yield ['https://foo.com?a=b', 'foo=bar+bar', 'https://foo.com?a=b&foo=bar+bar'];
        yield ['https://foo.com/page?aa=bb', 'foo=bar&biz=2', 'https://foo.com/page?aa=bb&foo=bar&biz=2'];
        yield ['https://foo.com/page?aa=bb#link', 'foo=bar&biz=2', 'https://foo.com/page?aa=bb&foo=bar&biz=2#link'];
        yield ['https://foo.com/page?aa=bb', 'foo=bar&aa=2', 'https://foo.com/page?aa=bb&foo=bar'];
        yield ['https://foo.com/page?aa=b+b', 'foo=bar&aa=2', 'https://foo.com/page?aa=b+b&foo=bar'];
        yield ['https://foo.com/page?aa=b%20b', 'foo=bar&aa=2', 'https://foo.com/page?aa=b%20b&foo=bar'];
        yield ['https://foo.com/page?aa=b+b', 'foo=bar&cc=2+2', 'https://foo.com/page?aa=b+b&foo=bar&cc=2+2'];
        yield ['https://foo.com/page?aa=b%20b', 'foo=bar&cc=2%202', 'https://foo.com/page?aa=b%20b&foo=bar&cc=2%202'];
    }

    /**
     * @dataProvider provideModeIgnore
     */
    public function testModeIgnore(string $url, string $queryString, string $expected): void
    {
        $output = UrlUtils::appendQueryString($url, $queryString, UrlUtils::APPEND_QUERY_STRING_IGNORE_DUPLICATE);
        $this->assertEquals($expected, $output);
    }

    /**
     * @dataProvider provideModeReplace
     */
    public function testModeReplace(string $url, string $queryString, string $expected): void
    {
        $output = UrlUtils::appendQueryString($url, $queryString, UrlUtils::APPEND_QUERY_STRING_REPLACE_DUPLICATE);
        $this->assertEquals($expected, $output);
    }

    /**
     * @dataProvider provideModeSkip
     */
    public function testModeSkip(string $url, string $queryString, string $expected): void
    {
        $output = UrlUtils::appendQueryString($url, $queryString, UrlUtils::APPEND_QUERY_STRING_SKIP_DUPLICATE);
        $this->assertEquals($expected, $output);
    }
}
