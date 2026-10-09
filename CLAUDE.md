# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`agungsugiarto/codeigniter4-socialite` is a CodeIgniter 4 port of Laravel Socialite (OAuth 2 login via GitHub, Google, Facebook, LinkedIn, GitLab, Bitbucket, Twitter). Namespace `Fluent\Socialite\` maps to `src/`; tests are `Fluent\Socialite\Tests\` in `tests/`. Supports PHP ^7.2|^8.0 (CI runs 7.4–8.4), so avoid newer syntax. Development here resolves to CodeIgniter 4.7 / PHPUnit 9.6.

## Commands

```sh
composer install
composer test                          # phpunit (alias for `phpunit`)
vendor/bin/phpunit tests/OAuthTwoTest.php
vendor/bin/phpunit --filter test_name  # single test
composer cs-check                      # phpcs (laminas standard); NOT clean on the existing code and not run in CI
composer cs-fix                        # phpcbf — don't run blindly: it adds declare(strict_types=1), which breaks the tests
```

PHPUnit bootstraps CodeIgniter's own test bootstrap from `vendor/codeigniter4/framework`, and tests extend `CIUnitTestCase`. `phpunit.xml.dist` writes coverage/log output to `build/`.

## Architecture

- **Entry points**: `Config\Services::socialite()` returns a shared `SocialiteManager`; the `Facades\Socialite` class is a thin `__callStatic` proxy to that shared service (it is not a Laravel-style container facade). Users call `Socialite::driver('github')->redirect()` / `->user()`.
- **`SocialiteManager`** (Laravel-style manager): `driver($name)` caches providers and resolves them via `create{Name}Driver()` methods (name is `ucfirst`ed, so `linkedin` → `createLinkedinDriver`) or via custom creators registered with `extend()`. To add a built-in provider, add a `Two\XProvider` plus a matching `createXDriver()` method and a `services` entry in `Config/Socialite.php`. `buildProvider()` constructs providers from `Services::request()` and the config entry (`client_id`, `client_secret`, `redirect`, optional `guzzle`).
- **Config**: `Config\Socialite::$services` holds credentials. Apps are expected to copy it to `app/Config/Socialite.php` and extend the package class (see README); `SocialiteManager` loads it through `Factories::config('Socialite')`.
- **`Two\AbstractProvider`**: the OAuth2 flow — builds the auth URL, stores `state` (and PKCE verifier) in the CI session, validates state on callback (`InvalidStateException`), exchanges the code with Guzzle, fetches the user. Concrete providers implement `getAuthUrl`, `getTokenUrl`, `getUserByToken`, `mapUserToObject`. Supports `scopes()/setScopes()`, `with()`, `stateless()`, `usesPKCE()`, `userFromToken()`.
- **Errors**: `InvalidStateException` (state mismatch) and `AuthorizationException` (provider `error` param, missing/malformed token response) are thrown from `user()`. Providers that need the PKCE verifier hash it as the *raw* digest (`hash('sha256', $v, true)`).
- **`AbstractUser` / `Two\User`**: normalized user object returned by providers (`getId`, `getEmail`, `token`, `refreshToken`, …).
- **`Helpers\Arr` / `Helpers\Str`**: small local replacements for Laravel's helpers; use these rather than adding Laravel dependencies.
- **Contracts** (`FactoryInterface`, `ProviderInterface`, `UserInterface`) define the public surface.

## Testing notes

`tests/Fixtures/*Stub.php` are provider stubs that override HTTP/user mapping so the OAuth flow can be tested with Mockery and no network access. Mocked HTTP responses must be real PSR-7 objects (`GuzzleHttp\Psr7\Response`), since `getBody()` returns a stream. Tests that need config inject it with `Factories::injectMock('config', 'Socialite', ...)`.

## Porting from Laravel

This is a fork, so when syncing upstream changes, translate Laravel `Request`/`Session`/`redirect()`/`Arr`/`Str` usage to the CodeIgniter equivalents (`CodeIgniter\HTTP\RequestInterface`, `Services::session()`, `redirect()`) and the local helpers. Only OAuth 2 is implemented (no `One\` namespace).

The repo's default branch is `5.x` (there is no `main`).
