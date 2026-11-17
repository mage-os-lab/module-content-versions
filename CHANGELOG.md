# Changelog

All notable changes to this module are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-17

### Added

- *Planned Versions* section on the CMS page and block edit forms: updates with a name, a start date, an optional end date and the content to show during that period (title and content; for pages also content heading and meta fields).
- A cron job (every minute) that puts the update live at its start date and restores the previous content at its end date; without an end date the change is permanent.
- Signed storefront preview link, which stops working once the update is finished or deleted, and is never stored in the full page cache.
- Overlapping updates of the same content are refused; editing an active update goes live immediately, deleting it restores the previous content.
- Admin grid under *Content > Elements > Planned Content Versions*, protected by the ACL resource `MageOS_ContentVersions::update`; saving or deleting an update also requires the right to save the targeted content (*Save Page* or *Blocks*).
- Admin texts translated into French, German, Spanish and Italian.
- Supports Mage-OS 3.6 and 4.x / Magento Open Source 2.4.9 (`magento/framework` ^103.0.9), PHP 8.3 to 8.5.
