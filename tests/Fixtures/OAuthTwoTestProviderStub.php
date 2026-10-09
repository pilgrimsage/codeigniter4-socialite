<?php

namespace Fluent\Socialite\Tests\Fixtures;

use Fluent\Socialite\Two\AbstractProvider;
use Fluent\Socialite\Two\User;
use GuzzleHttp\Client;
use Mockery as m;
use Mockery\MockInterface;
use stdClass;

class OAuthTwoTestProviderStub extends AbstractProvider
{
    /** @var Client|MockInterface */
    public $http;

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('http://auth.url', $state);
    }

    protected function getTokenUrl()
    {
        return 'http://token.url';
    }

    protected function getUserByToken($token)
    {
        return ['id' => 'foo'];
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->map(['id' => $user['id']]);
    }

    /**
     * Get a fresh instance of the Guzzle HTTP client.
     *
     * @return Client|MockInterface
     */
    protected function getHttpClient()
    {
        if ($this->http) {
            return $this->http;
        }

        return $this->http = m::mock(stdClass::class);
    }
}
