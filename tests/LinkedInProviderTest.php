<?php

namespace Fluent\Socialite\Tests;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Fluent\Socialite\Two\LinkedInProvider;
use Fluent\Socialite\Two\User;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery as m;

use function json_encode;

class LinkedInProviderTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        m::close();
    }

    public function test_it_can_map_a_user_without_an_email_address()
    {
        $request = Services::request(null, false);
        $request->setGlobal('get', ['code' => 'fake-code']);

        $accessTokenResponse = new Response(200, [], json_encode(['access_token' => 'fake-token']));

        $basicProfileResponse = new Response(200, [], json_encode(['id' => $userId = 1]));

        // Make sure email address response contains no values.
        $emailAddressResponse = new Response(200, [], json_encode(['elements' => []]));

        $guzzle = m::mock(Client::class);
        $guzzle->expects('post')->andReturns($accessTokenResponse);
        $guzzle->allows('get')->with('https://api.linkedin.com/v2/me', [
            'headers' => [
                'Authorization'             => 'Bearer fake-token',
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
            'query'   => [
                'projection' => '(id,firstName,lastName,profilePicture(displayImage~:playableStreams))',
            ],
        ])->andReturns($basicProfileResponse);
        $guzzle->allows('get')->with('https://api.linkedin.com/v2/emailAddress', [
            'headers' => [
                'Authorization'             => 'Bearer fake-token',
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
            'query'   => [
                'q'          => 'members',
                'projection' => '(elements*(handle~))',
            ],
        ])->andReturns($emailAddressResponse);

        $provider = new LinkedInProvider($request, 'client_id', 'client_secret', 'redirect');
        $provider->stateless();
        $provider->setHttpClient($guzzle);

        $user = $provider->user();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($userId, $user->getId());
        $this->assertNull($user->getEmail());
    }
}
