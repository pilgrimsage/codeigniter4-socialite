<?php

namespace Fluent\Socialite\Tests;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Fluent\Socialite\Two\AbstractProvider;
use Fluent\Socialite\Two\BitbucketProvider;
use Fluent\Socialite\Two\FacebookProvider;
use Fluent\Socialite\Two\GithubProvider;
use Fluent\Socialite\Two\GitlabProvider;
use Fluent\Socialite\Two\GoogleProvider;
use Fluent\Socialite\Two\LinkedInOpenIdProvider;
use Fluent\Socialite\Two\TwitterProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery as m;

use function hash_hmac;
use function json_encode;

class ProvidersTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session()->remove(['state', 'code_verifier']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        m::close();
    }

    private function provider(string $class, ?Client $guzzle = null): AbstractProvider
    {
        $request = Services::request(null, false);
        $request->setGlobal('get', ['code' => 'fake-code']);

        $provider = (new $class($request, 'client_id', 'client_secret', 'https://example.com/cb'))->stateless();

        if ($guzzle) {
            $provider->setHttpClient($guzzle);
        }

        return $provider;
    }

    private function json($body): Response
    {
        return new Response(200, [], json_encode($body));
    }

    private function location(AbstractProvider $provider): string
    {
        return $provider->redirect()->getHeaderLine('Location');
    }

    public function test_google_uses_the_current_endpoints()
    {
        $location = $this->location($this->provider(GoogleProvider::class));

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('scope=openid+profile+email', $location);
    }

    public function test_twitter_redirects_with_pkce()
    {
        $request  = Services::request(null, false);
        $provider = new TwitterProvider($request, 'client_id', 'client_secret', 'https://example.com/cb');
        $location = $this->location($provider);

        $this->assertStringStartsWith('https://twitter.com/i/oauth2/authorize?', $location);
        $this->assertStringContainsString('code_challenge_method=S256', $location);
        $this->assertNotEmpty(session()->get('code_verifier'));
    }

    public function test_facebook_uses_a_current_graph_version_and_only_requests_permitted_fields()
    {
        $guzzle = m::mock(Client::class);
        $guzzle->expects('get')->with('https://graph.facebook.com/v23.0/me', m::on(static function ($options) {
            return $options['query']['fields'] === 'name,email'
                && $options['query']['access_token'] === 'tok'
                && $options['query']['appsecret_proof'] === hash_hmac('sha256', 'tok', 'client_secret');
        }))->andReturns($this->json(['id' => '42', 'name' => 'Jane', 'email' => 'jane@example.com']));

        $user = $this->provider(FacebookProvider::class, $guzzle)->userFromToken('tok');

        $this->assertSame('42', $user->getId());
        $this->assertSame('jane@example.com', $user->getEmail());
        $this->assertSame('https://graph.facebook.com/v23.0/42/picture?type=normal', $user->getAvatar());
    }

    public function test_github_maps_the_user_and_resolves_the_primary_verified_email()
    {
        $headers = ['headers' => ['Accept' => 'application/vnd.github.v3+json', 'Authorization' => 'token tok']];

        $guzzle = m::mock(Client::class);
        $guzzle->expects('get')->with('https://api.github.com/user', $headers)
            ->andReturns($this->json(['id' => 1, 'login' => 'octocat', 'name' => 'The Octocat', 'avatar_url' => 'https://a/1']));
        $guzzle->expects('get')->with('https://api.github.com/user/emails', $headers)
            ->andReturns($this->json([
                ['email' => 'unverified@example.com', 'primary' => true, 'verified' => false],
                ['email' => 'octo@example.com', 'primary' => true, 'verified' => true],
            ]));

        $user = $this->provider(GithubProvider::class, $guzzle)->userFromToken('tok');

        $this->assertSame('octocat', $user->getNickname());
        $this->assertSame('octo@example.com', $user->getEmail());
        $this->assertSame('tok', $user->token);
    }

    public function test_github_still_returns_the_user_when_the_email_endpoint_fails()
    {
        $guzzle = m::mock(Client::class);
        $guzzle->allows('get')->with('https://api.github.com/user', m::any())
            ->andReturns($this->json(['id' => 1, 'login' => 'octocat', 'avatar_url' => 'https://a/1']));
        $guzzle->allows('get')->with('https://api.github.com/user/emails', m::any())
            ->andThrow(new RequestException('forbidden', new Request('GET', 'https://api.github.com/user/emails')));

        $user = $this->provider(GithubProvider::class, $guzzle)->userFromToken('tok');

        $this->assertNull($user->getEmail());
    }

    public function test_gitlab_uses_the_v4_api_with_a_bearer_token()
    {
        $guzzle = m::mock(Client::class);
        $guzzle->expects('get')->with('https://gitlab.example.com/api/v4/user', [
            'headers' => ['Authorization' => 'Bearer tok'],
        ])->andReturns($this->json(['id' => 7, 'username' => 'tanuki', 'name' => 'Tanuki', 'email' => 't@example.com', 'avatar_url' => 'https://a/7']));

        $provider = $this->provider(GitlabProvider::class, $guzzle)->setHost('https://gitlab.example.com/');
        $user     = $provider->userFromToken('tok');

        $this->assertSame('tanuki', $user->getNickname());
        $this->assertSame('t@example.com', $user->getEmail());
    }

    public function test_bitbucket_authenticates_with_a_header_instead_of_a_query_string()
    {
        $headers = ['headers' => ['Authorization' => 'Bearer tok']];

        $guzzle = m::mock(Client::class);
        $guzzle->expects('get')->with('https://api.bitbucket.org/2.0/user', $headers)
            ->andReturns($this->json(['uuid' => '{u}', 'username' => 'bb', 'display_name' => 'BB', 'links' => ['avatar' => ['href' => 'https://a/bb']]]));
        $guzzle->expects('get')->with('https://api.bitbucket.org/2.0/user/emails', $headers)
            ->andReturns($this->json(['values' => [['type' => 'email', 'is_primary' => true, 'is_confirmed' => true, 'email' => 'bb@example.com']]]));

        $user = $this->provider(BitbucketProvider::class, $guzzle)->userFromToken('tok');

        $this->assertSame('{u}', $user->getId());
        $this->assertSame('bb@example.com', $user->getEmail());
        $this->assertSame('https://a/bb', $user->getAvatar());
    }

    public function test_linkedin_openid_maps_the_userinfo_response()
    {
        $guzzle = m::mock(Client::class);
        $guzzle->expects('get')->with('https://api.linkedin.com/v2/userinfo', [
            'headers' => ['Authorization' => 'Bearer tok'],
        ])->andReturns($this->json([
            'sub'         => 'abc',
            'name'        => 'Jane Doe',
            'given_name'  => 'Jane',
            'family_name' => 'Doe',
            'email'       => 'jane@example.com',
            'picture'     => 'https://a/jane',
        ]));

        $user = $this->provider(LinkedInOpenIdProvider::class, $guzzle)->userFromToken('tok');

        $this->assertSame('abc', $user->getId());
        $this->assertSame('Jane Doe', $user->getName());
        $this->assertSame('Jane', $user->first_name);
        $this->assertSame('jane@example.com', $user->getEmail());
        $this->assertSame('https://a/jane', $user->getAvatar());
    }

    public function test_linkedin_openid_requests_the_openid_scopes()
    {
        $location = $this->location($this->provider(LinkedInOpenIdProvider::class));

        $this->assertStringContainsString('scope=openid+profile+email', $location);
    }
}
