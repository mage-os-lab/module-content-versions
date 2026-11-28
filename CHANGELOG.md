# Changelog

All notable changes to this module are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/).

## [1.2.0] - 2026-11-28

CMS pages and blocks keep revisions to compare and restore, finished planned versions can be restored or duplicated, and planned versions get a calendar view.

Upgrade: revisions are recorded from the first save after the update: content saved before has none. The number kept is set in *Stores > Configuration > General > Content Management > Content Versions*. The update deletes the planned versions left by pages and blocks deleted before.

### Added

- *Restore* on a finished planned version: its content goes live again now, with no end date. Restored while another version is live, it stays live when that version ends, even if both have the same content; restoring a revision does the same.
- *Duplicate* on a planned version: opens a new version with its name and content, to plan it on other dates.
- A revision of the content of a CMS page or block at each save, from the admin, the cron, an import or the API. The *Revisions* section of the edit form lists them, compares one with the current content field by field and restores it. Deleting a page or block deletes its revisions. The *Planned Versions* and *Revisions* sections are hidden from admin roles without the *Planned Content Versions* resource.
- *Revisions Kept per Page or Block* setting (20 by default; 0 records none): older revisions are deleted.
- *Calendar View* of the planned versions, by month, from *Content > Elements > Planned Content Versions*.
- Translations of the new texts in French, German, Spanish and Italian.

### Fixed

- Deleting a CMS page or block, one by one or from the grid's mass action, left its planned versions in the grid and the calendar. They are now deleted with it, whatever their status, and the upgrade deletes those left by pages and blocks deleted before.
- A planned version that could not be applied was logged again at every cron run. The same error is now logged once; it is logged again if the error changes or after the version was applied.

## [1.1.0] - 2026-11-24

A planned version replaces only the fields it changes, so edits made around it are kept, and a version that fails no longer blocks the others.

Upgrade: preview links made before this version stop working; click *Preview* again to get a new one. Versions planned before this version still replace every field: open and save them once to keep only the fields they change.

### Added

- `bin/magento content-versions:run` command, which starts and ends the due versions now and exits with an error code when one could not be applied.
- *Last Error* column in *Content > Elements > Planned Content Versions*, also shown in the *Planned Versions* section: why a version could not start or end.

### Changed

- A planned version stores and replaces only the fields changed in it. An edit made to another field of the page or block, before or during the version, is no longer overwritten.
- At the end of a version, a field edited directly on the page or block while it was live keeps that edit instead of going back to the previous content.
- A planned version that changes nothing is refused.
- The preview opens in a store view the page or block is assigned to, instead of always the default one.
- Preview links expire after 7 days. *Preview* in the admin signs a new link at each click.

### Fixed

- A version that could not be applied stopped all the following ones, at every cron run. It is now skipped, logged once and retried at the next run.
- A failure between the content change and the status change of a version could make the next run keep the planned content as "the previous content", which was then lost. Both now change together or not at all, and a version applied by two runs at the same time is applied once.
- A version no longer starts while the previous version of the same content could not be ended.
- Deleting an active version whose previous content cannot be restored shows the error instead of an error page.
- The versions grid no longer loads one CMS page per row.

## [1.0.0] - 2026-11-17

### Added

- *Planned Versions* section on the CMS page and block edit forms: updates with a name, a start date, an optional end date and the content to show during that period (title and content; for pages also content heading and meta fields).
- A cron job (every minute) that puts the update live at its start date and restores the previous content at its end date; without an end date the change is permanent.
- Signed storefront preview link, which stops working once the update is finished or deleted, and is never stored in the full page cache.
- Overlapping updates of the same content are refused; editing an active update goes live immediately, deleting it restores the previous content.
- Admin grid under *Content > Elements > Planned Content Versions*, protected by the ACL resource `MageOS_ContentVersions::update`; saving or deleting an update also requires the right to save the targeted content (*Save Page* or *Blocks*).
- Admin texts translated into French, German, Spanish and Italian.
- Supports Mage-OS 3.6 and 4.x / Magento Open Source 2.4.9 (`magento/framework` ^103.0.9), PHP 8.3 to 8.5.
