<?php

namespace Fluent\Socialite\Two;

use Fluent\Socialite\Helpers\Arr;

use function json_decode;

/**
 * LinkedIn "Sign In with LinkedIn using OpenID Connect".
 *
 * Replaces the legacy r_liteprofile / r_emailaddress flow of {@see LinkedInProvider}.
 */
class LinkedInOpenIdProvider extends AbstractProvider
{
    /**
     * {@inheritdoc}
     */
    protected $scopes = ['openid', 'profile', 'email'];

    /**
     * {@inheritdoc}
     */
    protected $scopeSeparator = ' ';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://www.linkedin.com/oauth/v2/authorization', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://api.linkedin.com/v2/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return (array) json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'              => Arr::get($user, 'sub'),
            'nickname'        => null,
            'name'            => Arr::get($user, 'name'),
            'first_name'      => Arr::get($user, 'given_name'),
            'last_name'       => Arr::get($user, 'family_name'),
            'email'           => Arr::get($user, 'email'),
            'avatar'          => $avatar = Arr::get($user, 'picture'),
            'avatar_original' => $avatar,
        ]);
    }
}
