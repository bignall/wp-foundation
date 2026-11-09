# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Project website with all guides and a live deactivation survey demo: https://bignall.github.io/wp-foundation/
- `homepage` in `composer.json`, linking Packagist to the website.

## [0.1.1] - 2026-11-09

### Fixed

- Default options now pass a boolean `autoload` to `add_option()`, matching the WordPress 6.6+ signature (still supported on 6.4).
- The filtered list of deactivation reasons still drops anything that isn't a `Reason`, with static analysis passing.

### Changed

- CI uses `actions/checkout@v7`, `actions/setup-node@v7` and `ramsey/composer-install@v4`, which run on Node 24.

## [0.1.0] - 2026-11-09

### Added

- `Plugin` builder with activation, deactivation, upgrade and uninstall handling.
- Requirement checks for PHP and WordPress versions, PHP extensions, other plugins and custom conditions.
- Built-in tasks: options, roles and capabilities, cron events and schedules, rewrite rules, custom tables.
- Versioned migrations with resumable progress, an upgrade lock and an admin notice when a migration fails.
- Multisite support: network activation and uninstall, new-site setup, table cleanup on site deletion.
- Deactivation survey with configurable reasons, local storage, a remote handler and consent for the site URL.

[Unreleased]: https://github.com/bignall/wp-foundation/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/bignall/wp-foundation/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/bignall/wp-foundation/releases/tag/v0.1.0
