# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Flash notice items can be links: pass `array( 'text' => ..., 'url' => ... )` instead of a string,
  and it's printed as `<a>` with the text escaped by `esc_html()` and the URL by `esc_url()`.
  String items stay plain text.

### Fixed

- `taxonomies()` uninstall left WordPress's `{taxonomy}_children` option behind for hierarchical
  taxonomies. It's now deleted with the terms.

## [0.1.2] - 2026-11-09

### Added

- `legacyVersion()`: recognise installs from before a plugin adopted the framework (by an option
  name or a callback), and upgrade them from that version so their migrations run, rather than
  treating them as fresh installs.
- `options( ..., merge: true )`: fill in keys added to array option defaults on existing sites,
  without changing stored values.
- `postTypes()` and `taxonomies()`: on uninstall, delete the plugin's posts in every status
  (including `auto-draft` and `trash`) with their meta, and its terms, registering each taxonomy
  temporarily. Runs before custom tables are dropped.
- `Plugin::requestRewriteFlush()`: rebuild rewrite rules at the end of the next `init`, whether or
  not `flushRewriteRules()` is used.
- `Plugin::notices()->flash()`: per-user admin notices that survive a redirect.
- Release build guide and `bin/build-release.sh` in the example plugin, producing a small zip
  without Composer's dev tools and failing if any reference is left unprefixed.
- Guidance on how Strauss rewrites a plugin's own `use` statements, and how to re-run it.
- Project website with all guides and a live deactivation survey demo: https://bignall.github.io/wp-foundation/
- `homepage` in `composer.json`, linking Packagist to the website.

### Fixed

- The deactivation survey never appeared in plugins that prefix the library with Strauss: its
  script and styles were outside `src/`, so they weren't copied. They now live in
  `src/Feedback/assets/`, and the survey is skipped rather than half-loaded if they're missing.
- The prefixing guide required `^1.0`, which no release satisfies. It now uses `^0.1`.
- The example plugin built its survey inline and never used its `survey()` method, so the
  `ACME_BOOKINGS_FEEDBACK_URL` handler never took effect.

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

[Unreleased]: https://github.com/bignall/wp-foundation/compare/0.1.2...HEAD
[0.1.2]: https://github.com/bignall/wp-foundation/compare/v0.1.1...0.1.2
[0.1.1]: https://github.com/bignall/wp-foundation/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/bignall/wp-foundation/releases/tag/v0.1.0
