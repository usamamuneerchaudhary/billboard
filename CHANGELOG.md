# Changelog

All notable changes to Billboard are documented here. This project follows [Semantic Versioning](https://semver.org/).

## 1.1.1

### Changed

- Relicensed Billboard under the MIT License. Packagist 1.0 is still the earlier proprietary release, because the 1.1 tag declared `"version": "1.0"` and Packagist skipped it.
- Removed the `version` field from `composer.json`. Composer and Packagist take the version from the git tag, so a tag can no longer publish as a different version.
- Corrected the README to match the package: PHP 8.3 or later, `php artisan vendor:publish --tag=billboard` before migrate, and the MIT license.
- Listed the Advertisement field, the Markdown "Insert advertisement" action, and the Advertisement set for Bard and Replicator. Those shipped in 1.0.

## 1.0

### Added

- Placements with weighted or ordered rotation, several ads at once, reserved sizes, a disclosure label and fallback HTML.
- Image, text and HTML ads, with a `%%CLICK_URL%%` macro for tracked links in HTML ads.
- Start and end scheduling, path, device and site targeting, total impression and click limits, and a daily limit per visitor.
- Choosing ads on the server or in the browser, so rotation and schedules work with full static caching.
- First-party reporting of viewable impressions and clicks, with period comparison, filters, CSV export and a dashboard widget.
- Bot filtering, Global Privacy Control and Do Not Track support, rate limiting and scheduled data retention.
- `{{ billboard }}` Antlers tag, `@billboard` Blade directive, and an Ad placements dictionary for Dictionary fields.
- An Advertisement field, a Markdown "Insert advertisement" action, and an Advertisement set for Bard and Replicator.
- Control panel screens for ads, placements and reports, with enable, disable and delete actions.
- Permissions for viewing, editing, creating and deleting ads, editing HTML ads, managing placements and viewing reports.
- `AdSaved`, `AdDeleted`, `PlacementSaved` and `PlacementDeleted` events, and an option to clear the static cache when ads change.
