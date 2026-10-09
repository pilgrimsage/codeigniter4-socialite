<?php

namespace Fluent\Socialite\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Fluent\Socialite\Helpers\Arr;
use Fluent\Socialite\Helpers\Str;

use function strlen;

class HelpersTest extends CIUnitTestCase
{
    public function test_str_studly()
    {
        $this->assertSame('Github', Str::studly('github'));
        $this->assertSame('LinkedinOpenid', Str::studly('linkedin-openid'));
        $this->assertSame('MyDriver', Str::studly('my_driver'));
    }

    public function test_str_random_has_the_requested_length_and_is_url_safe()
    {
        $value = Str::random(96);

        $this->assertSame(96, strlen($value));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $value);
        $this->assertNotSame($value, Str::random(96));
    }

    public function test_str_starts_with()
    {
        $this->assertTrue(Str::startsWith('/auth/callback', '/'));
        $this->assertFalse(Str::startsWith('https://example.com', '/'));
        $this->assertFalse(Str::startsWith('abc', ''));
    }

    public function test_arr_get_supports_dot_notation_and_defaults()
    {
        $array = ['a' => ['b' => ['c' => 1]], 'x.y' => 2];

        $this->assertSame(1, Arr::get($array, 'a.b.c'));
        $this->assertSame(2, Arr::get($array, 'x.y'));
        $this->assertSame('d', Arr::get($array, 'a.z', 'd'));
        $this->assertSame('d', Arr::get(null, 'a', 'd'));
    }
}
