# Upgrade Guide

## Upgrading To The Next Release From 5.0

### LinkedIn

LinkedIn retired the `r_liteprofile` and `r_emailaddress` scopes used by the `linkedin` driver. Add a `linkedin-openid` entry to `Config\Socialite::$services` and switch to `Socialite::driver('linkedin-openid')`.

### Facebook

The default Graph API version is now `v23.0` and the default `fields` are `name` and `email`. If you relied on `gender`, `verified` or `link`, request them with `->fields([...])` (they require Facebook app review) or pin an older version with `->usingGraphVersion()`.

### Exceptions

`user()` can now throw `Fluent\Socialite\Two\AuthorizationException` (access denied, no access token) in addition to `InvalidStateException`.

### Custom Providers

If you extend `TwitterProvider`, `getAuthUrl()` must now be declared `protected` (it was accidentally `public`).

## Upgrading To 5.0 From 4.x

### Minimum PHP Version

PHP 7.2 is now the minimum required version.

### Minimum Laravel Version

Laravel 6.0 is now the minimum required version.


## Upgrading To 4.0 From 3.x

### PHP & Laravel Version Requirements

Like the latest releases of the Laravel framework, Laravel Socialite now requires PHP >= 7.1.3. We encourage you to upgrade to the latest versions of PHP and Laravel before upgrading to Socialite 4.0.

### LinkedInProvider Changes

The `LinkedInProvider` was updated to make use of the latest API version of LinkedIn. The provider will now only retrieve the most basic fields and removes the `fields` method from the provider. Please review [the related PR](https://github.com/laravel/socialite/pull/310) for all details and changes.
