<?php

namespace Fluent\Socialite\Tests\Fixtures;

class OAuthTwoWithPKCETestProviderStub extends OAuthTwoTestProviderStub
{
    protected $usesPKCE = true;
}