<?php

namespace Fluent\Socialite\Tests;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Fluent\Socialite\Config\Services;
use Fluent\Socialite\Config\Socialite as SocialiteConfig;
use Fluent\Socialite\SocialiteManager;
use Fluent\Socialite\Two\BitbucketProvider;
use Fluent\Socialite\Two\FacebookProvider;
use Fluent\Socialite\Two\GithubProvider;
use Fluent\Socialite\Two\GitlabProvider;
use Fluent\Socialite\Two\GoogleProvider;
use Fluent\Socialite\Two\LinkedInOpenIdProvider;
use Fluent\Socialite\Two\LinkedInProvider;
use Fluent\Socialite\Two\TwitterProvider;
use InvalidArgumentException;
use ReflectionProperty;

class SocialiteManagerTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Factories::reset('config');
    }

    private function manager(array $services): SocialiteManager
    {
        $config           = new SocialiteConfig();
        $config->services = $services;

        Factories::injectMock('config', 'Socialite', $config);

        return new SocialiteManager(new Factories(), true);
    }

    private function credentials(array $extra = []): array
    {
        return $extra + ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'https://example.com/callback'];
    }

    private function property(object $object, string $name)
    {
        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * @dataProvider drivers
     */
    public function test_it_can_instantiate_every_built_in_driver(string $name, string $class)
    {
        $provider = Services::socialite(false)->driver($name);

        $this->assertInstanceOf($class, $provider);
    }

    public function drivers(): array
    {
        return [
            'github'          => ['github', GithubProvider::class],
            'facebook'        => ['facebook', FacebookProvider::class],
            'google'          => ['google', GoogleProvider::class],
            'linkedin'        => ['linkedin', LinkedInProvider::class],
            'linkedin-openid' => ['linkedin-openid', LinkedInOpenIdProvider::class],
            'bitbucket'       => ['bitbucket', BitbucketProvider::class],
            'gitlab'          => ['gitlab', GitlabProvider::class],
            'twitter'         => ['twitter', TwitterProvider::class],
        ];
    }

    public function test_drivers_are_cached()
    {
        $manager = Services::socialite(false);

        $this->assertSame($manager->driver('github'), $manager->driver('github'));
        $this->assertCount(1, $manager->getDrivers());

        $manager->forgetDrivers();

        $this->assertSame([], $manager->getDrivers());
    }

    public function test_it_rejects_unsupported_drivers()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [myspace] not supported.');

        Services::socialite(false)->driver('myspace');
    }

    public function test_it_requires_a_driver_name()
    {
        $this->expectException(InvalidArgumentException::class);

        Services::socialite(false)->driver();
    }

    public function test_it_supports_custom_driver_creators()
    {
        $manager = Services::socialite(false);
        $manager->extend('custom', static function () {
            return 'custom-driver';
        });

        $this->assertSame('custom-driver', $manager->driver('custom'));
    }

    public function test_it_fails_clearly_when_a_service_is_not_configured()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No Socialite configuration found for [github]');

        $this->manager([])->driver('github');
    }

    public function test_it_fails_clearly_when_a_credential_option_is_missing()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing the [client_secret] option');

        $this->manager(['github' => ['client_id' => 'id', 'redirect' => '/cb']])->driver('github');
    }

    public function test_absolute_redirect_urls_are_kept_as_is()
    {
        $provider = $this->manager(['github' => $this->credentials()])->driver('github');

        $this->assertSame('https://example.com/callback', $this->property($provider, 'redirectUrl'));
    }

    public function test_relative_redirect_urls_are_resolved_to_a_fully_qualified_url()
    {
        $provider = $this->manager(['github' => $this->credentials(['redirect' => '/auth/callback'])])->driver('github');

        $redirectUrl = $this->property($provider, 'redirectUrl');

        $this->assertIsString($redirectUrl);
        $this->assertStringStartsWith('http://localhost:8080', $redirectUrl);
        $this->assertStringEndsWith('/auth/callback', $redirectUrl);
    }

    public function test_gitlab_supports_a_self_hosted_host()
    {
        $provider = $this->manager(['gitlab' => $this->credentials(['host' => 'https://git.example.com/'])])->driver('gitlab');

        $this->assertSame('https://git.example.com', $this->property($provider, 'host'));
    }

    public function test_custom_guzzle_options_are_forwarded_to_the_provider()
    {
        $provider = $this->manager(['github' => $this->credentials(['guzzle' => ['timeout' => 5]])])->driver('github');

        $this->assertSame(['timeout' => 5], $this->property($provider, 'guzzle'));
    }
}
