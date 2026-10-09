# Release Notes

## [Unreleased](https://github.com/agungsugiarto/codeigniter4-socialite/compare/v5.0.0...5.x)

### Fixed

- PKCE: the `code_challenge` was derived from the hex digest instead of the raw SHA-256 digest, producing an invalid S256 challenge. Providers using PKCE (e.g. Twitter) now send a valid one.
- Relative `redirect` URLs in the configuration are now resolved with `site_url()`; previously a `RedirectResponse` object was produced instead of a URL.
- GitLab now uses the `/api/v4` API (v3 was removed) and sends the token in the `Authorization` header.
- Bitbucket sends the access token in the `Authorization` header rather than the query string.
- Google uses the current OAuth endpoints (`/o/oauth2/v2/auth` and `https://oauth2.googleapis.com/token`).
- Facebook defaults to Graph API `v23.0` and only requests the `name` and `email` fields (`gender`, `verified` and `link` are no longer available by default).
- The OAuth `state` is compared in constant time and a missing `state` is rejected.
- `approvedScopes` is an empty array, not `['']`, when the provider reports no scopes.
- No more "creation of dynamic property" deprecations on PHP 8.2+.

### Added

- `linkedin-openid` driver (`LinkedInOpenIdProvider`) for LinkedIn's OpenID Connect flow.
- `AuthorizationException`, thrown when the provider redirects back with an `error` (e.g. `access_denied`), returns no access token, or returns a malformed token response.
- `AbstractProvider::getRefreshTokenResponse()` and `AbstractProvider::setSession()`.
- Clear `InvalidArgumentException`s for missing service configuration instead of "undefined index" errors.
- Driver names containing `-` or `_` are resolved to `create{Studly}Driver()` methods.

### Changed

- `TwitterProvider::getAuthUrl()` is now `protected`, like every other provider.
- Test suite rewritten: previously commented-out tests restored, plus coverage for the manager, every provider and the helpers.
- CI runs PHP 7.4 – 8.4 with current GitHub Actions.
- Guzzle requirement raised to `^6.5.5|^7.0`.

## v5.0.0 (2021-08-18)

- Initial release
