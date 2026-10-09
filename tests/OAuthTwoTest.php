<?php

namespace Fluent\Socialite\Tests;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\CIUnitTestCase;
use Fluent\Socialite\Tests\Fixtures\FacebookTestProviderStub;
use Fluent\Socialite\Tests\Fixtures\OAuthTwoTestProviderStub;
use Fluent\Socialite\Tests\Fixtures\OAuthTwoWithPKCETestProviderStub;
use Fluent\Socialite\Two\AuthorizationException;
use Fluent\Socialite\Two\InvalidStateException;
use Fluent\Socialite\Two\User;
use GuzzleHttp\Psr7\Response;
use Mockery as m;
use stdClass;

use function base64_encode;
use function hash;
use function json_encode;
use function rtrim;
use function str_repeat;
use function strlen;
use function strtr;

class OAuthTwoTest extends CIUnitTestCase
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

    private function request(array $get = [])
    {
        $request = Services::request(null, false);
        $request->setGlobal('get', $get);

        return $request;
    }

    private function json(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public function testRedirectGeneratesTheProperRedirectResponseWithoutPKCE()
    {
        $provider = new OAuthTwoTestProviderStub($this->request(), 'client_id', 'client_secret', 'redirect');
        $response = $provider->redirect();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://auth.url?client_id=client_id&redirect_uri=redirect&scope=&response_type=code&state=' . session()->get('state'), $response->getHeaderLine('Location'));
    }

    public function testRedirectGeneratesTheProperRedirectResponseWithPKCE()
    {
        $provider = new OAuthTwoWithPKCETestProviderStub($this->request(), 'client_id', 'client_secret', 'redirect');
        $response = $provider->redirect();

        // RFC 7636 (S256): BASE64URL(SHA256(verifier)) over the *raw* digest, without padding.
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', session()->get('code_verifier'), true)), '+/', '-_'), '=');

        $this->assertSame(43, strlen($codeChallenge));
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://auth.url?client_id=client_id&redirect_uri=redirect&scope=&response_type=code&state=' . session()->get('state') . '&code_challenge=' . $codeChallenge . '&code_challenge_method=S256', $response->getHeaderLine('Location'));
    }

    public function testStatelessRedirectOmitsState()
    {
        $provider = (new OAuthTwoTestProviderStub($this->request(), 'client_id', 'client_secret', 'redirect'))->stateless();
        $response = $provider->redirect();

        $this->assertSame('http://auth.url?client_id=client_id&redirect_uri=redirect&scope=&response_type=code', $response->getHeaderLine('Location'));
        $this->assertNull(session()->get('state'));
    }

    public function testTokenRequestIncludesPKCECodeVerifier()
    {
        $state        = str_repeat('A', 40);
        $codeVerifier = str_repeat('V', 96);
        session()->set(['state' => $state, 'code_verifier' => $codeVerifier]);

        $provider       = new OAuthTwoWithPKCETestProviderStub($this->request(['state' => $state, 'code' => 'code']), 'client_id', 'client_secret', 'redirect_uri');
        $provider->http = m::mock(stdClass::class);
        $provider->http->expects('post')->with('http://token.url', [
            'headers'     => ['Accept' => 'application/json'],
            'form_params' => ['grant_type' => 'authorization_code', 'client_id' => 'client_id', 'client_secret' => 'client_secret', 'code' => 'code', 'redirect_uri' => 'redirect_uri', 'code_verifier' => $codeVerifier],
        ])->andReturns($this->json(['access_token' => 'access_token', 'refresh_token' => 'refresh_token', 'expires_in' => 3600]));

        $user = $provider->user();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('access_token', $user->token);
        $this->assertNull(session()->get('code_verifier'), 'The PKCE verifier must be single-use.');
    }

    public function testUserReturnsAUserInstanceForTheAuthenticatedRequest()
    {
        $state = str_repeat('A', 40);
        session()->set('state', $state);

        $provider       = new OAuthTwoTestProviderStub($this->request(['state' => $state, 'code' => 'code']), 'client_id', 'client_secret', 'redirect_uri');
        $provider->http = m::mock(stdClass::class);
        $provider->http->expects('post')->with('http://token.url', [
            'headers'     => ['Accept' => 'application/json'],
            'form_params' => ['grant_type' => 'authorization_code', 'client_id' => 'client_id', 'client_secret' => 'client_secret', 'code' => 'code', 'redirect_uri' => 'redirect_uri'],
        ])->andReturns($this->json(['access_token' => 'access_token', 'refresh_token' => 'refresh_token', 'expires_in' => 3600, 'scope' => 'read,write']));

        $user = $provider->user();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('foo', $user->id);
        $this->assertSame('access_token', $user->token);
        $this->assertSame('refresh_token', $user->refreshToken);
        $this->assertSame(3600, $user->expiresIn);
        $this->assertSame($user->id, $provider->user()->id, 'The resolved user is cached.');
        $this->assertNull(session()->get('state'), 'The state must be single-use.');
    }

    public function testApprovedScopesAreEmptyWhenTheProviderDoesNotReportAny()
    {
        $provider       = (new OAuthTwoTestProviderStub($this->request(['code' => 'code']), 'client_id', 'client_secret', 'redirect_uri'))->stateless();
        $provider->http = m::mock(stdClass::class);
        $provider->http->allows('post')->andReturns($this->json(['access_token' => 'access_token']));

        $this->assertSame([], $provider->user()->approvedScopes);
    }

    public function testUserReturnsAUserInstanceForTheAuthenticatedFacebookRequest()
    {
        $state = str_repeat('A', 40);
        session()->set('state', $state);

        $provider       = new FacebookTestProviderStub($this->request(['state' => $state, 'code' => 'code']), 'client_id', 'client_secret', 'redirect_uri');
        $provider->http = m::mock(stdClass::class);
        $provider->http->expects('post')->with('https://graph.facebook.com/v23.0/oauth/access_token', [
            'form_params' => ['grant_type' => 'authorization_code', 'client_id' => 'client_id', 'client_secret' => 'client_secret', 'code' => 'code', 'redirect_uri' => 'redirect_uri'],
        ])->andReturns($this->json(['access_token' => 'access_token', 'expires' => 5183085]));

        $user = $provider->user();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('foo', $user->id);
        $this->assertSame('access_token', $user->token);
        $this->assertNull($user->refreshToken);
        $this->assertSame(5183085, $user->expiresIn);
        $this->assertSame($user->id, $provider->user()->id);
    }

    public function testExceptionIsThrownIfStateIsInvalid()
    {
        $this->expectException(InvalidStateException::class);

        session()->set('state', str_repeat('A', 40));

        $provider = new OAuthTwoTestProviderStub($this->request(['state' => str_repeat('B', 40), 'code' => 'code']), 'client_id', 'client_secret', 'redirect');
        $provider->user();
    }

    public function testExceptionIsThrownIfStateIsNotSet()
    {
        $this->expectException(InvalidStateException::class);

        $provider = new OAuthTwoTestProviderStub($this->request(['state' => 'state', 'code' => 'code']), 'client_id', 'client_secret', 'redirect');
        $provider->user();
    }

    public function testExceptionIsThrownIfStateIsMissingFromTheCallback()
    {
        $this->expectException(InvalidStateException::class);

        session()->set('state', str_repeat('A', 40));

        $provider = new OAuthTwoTestProviderStub($this->request(['code' => 'code']), 'client_id', 'client_secret', 'redirect');
        $provider->user();
    }

    public function testExceptionIsThrownIfTheProviderReportsAnError()
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('access_denied (The user denied the request)');

        $provider = (new OAuthTwoTestProviderStub($this->request(['error' => 'access_denied', 'error_description' => 'The user denied the request']), 'client_id', 'client_secret', 'redirect'))->stateless();
        $provider->user();
    }

    public function testExceptionIsThrownIfTheTokenResponseHasNoAccessToken()
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('The code passed is incorrect or expired.');

        $provider       = (new OAuthTwoTestProviderStub($this->request(['code' => 'bad']), 'client_id', 'client_secret', 'redirect'))->stateless();
        $provider->http = m::mock(stdClass::class);
        $provider->http->allows('post')->andReturns($this->json(['error' => 'bad_verification_code', 'error_description' => 'The code passed is incorrect or expired.']));
        $provider->user();
    }

    public function testExceptionIsThrownIfTheTokenResponseIsMalformed()
    {
        $this->expectException(AuthorizationException::class);

        $provider       = (new OAuthTwoTestProviderStub($this->request(['code' => 'code']), 'client_id', 'client_secret', 'redirect'))->stateless();
        $provider->http = m::mock(stdClass::class);
        $provider->http->allows('post')->andReturns(new Response(200, [], '<html>Bad gateway</html>'));
        $provider->user();
    }

    public function testRefreshTokenRequest()
    {
        $provider       = new OAuthTwoTestProviderStub($this->request(), 'client_id', 'client_secret', 'redirect');
        $provider->http = m::mock(stdClass::class);
        $provider->http->expects('post')->with('http://token.url', [
            'headers'     => ['Accept' => 'application/json'],
            'form_params' => ['grant_type' => 'refresh_token', 'client_id' => 'client_id', 'client_secret' => 'client_secret', 'refresh_token' => 'old'],
        ])->andReturns($this->json(['access_token' => 'new', 'expires_in' => 60]));

        $this->assertSame(['access_token' => 'new', 'expires_in' => 60], $provider->getRefreshTokenResponse('old'));
    }
}
