<?php

namespace Fluent\Socialite\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Fluent\Socialite\Config\Services;
use Fluent\Socialite\Two\GithubProvider;

class SocialiteManagerTest extends CIUnitTestCase
{
    public function test_it_can_instantiate_the_github_driver()
    {
        $factory = Services::socialite();

        $provider = $factory->driver('github');

        $this->assertInstanceOf(GithubProvider::class, $provider);
    }
}