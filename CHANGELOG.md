# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `Plugin` builder with activation, deactivation, upgrade and uninstall handling.
- Requirement checks for PHP and WordPress versions, PHP extensions, other plugins and custom conditions.
- Built-in tasks: options, roles and capabilities, cron events and schedules, rewrite rules, custom tables.
- Versioned migrations with resumable progress, an upgrade lock and an admin notice when a migration fails.
- Multisite support: network activation and uninstall, new-site setup, table cleanup on site deletion.
- Deactivation survey with configurable reasons, local storage, a remote handler and consent for the site URL.
