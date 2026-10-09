<?php

namespace FLuent\Socialite\Tests\Fixtures;

use Fluent\Socialite\Two\FacebookProvider;
use GuzzleHttp\Client;
use Mockery as m;
use Mockery\MockInterface;
use stdClass;

class FacebookTestProviderStub extends FacebookProvider
{
    /** @var Client|MockInterface */
    public $http;

    protected function getUserByToken($token)
    {
        return ['id' => 'foo'];
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
