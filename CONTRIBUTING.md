# Contributing

Thanks for helping. Bug reports, docs fixes and pull requests are all welcome.

## Setup

```bash
git clone https://github.com/arbe/wp-foundation.git
cd wp-foundation
composer install
```

## Checks

Every pull request must pass:

```bash
composer lint          # WordPress Coding Standards (PSR-4 file names, camelCase methods)
composer analyse       # PHPStan
composer test:unit     # Brain Monkey unit tests
```

Integration tests need MySQL or MariaDB:

```bash
bin/install-wp-tests.sh wordpress_tests root root 127.0.0.1 latest
export WP_TESTS_DIR=/tmp/wordpress-develop/tests/phpunit
composer test:integration
WP_MULTISITE=1 composer test:integration
```

If you change the survey script or styles:

```bash
npm install && npx playwright install chromium && npm run test:browser
```

CI runs all of the above for PHP 8.1 to 8.4, and for WordPress 6.4 and latest, both single site
and multisite.

## Guidelines

- Keep the public API small and backwards compatible. Anything not documented in `docs/` is internal.
- Lifecycle tasks must be idempotent. Activation runs on every reactivation, and upgrades can repeat.
- Never delete user data outside `uninstall()`.
- Add a test with every behaviour change, and an entry under "Unreleased" in `CHANGELOG.md`.
- Use the text domain `wp-foundation` for strings in `src/`.

## License

By contributing, you agree that your contributions are licensed under GPL-2.0-or-later.
