# Content Versions for CMS Pages and Blocks

`mage-os/module-content-versions` (`MageOS_ContentVersions`) — a module for Mage-OS / Magento Open Source.

Schedules temporary or permanent content versions of CMS pages and CMS blocks, with a storefront preview.

- The page and block edit forms get a *Planned Versions* section listing the updates of that content.
- An update has a name, a start date, an optional end date, and the content to show during that period (title and content; for pages also content heading and meta fields). The content field supports Page Builder.
- An update only replaces the fields you changed in it. The other fields keep following the page or block, so a correction made there before or during the update is not lost.
- A cron job runs every minute: at the start date the live content is replaced; at the end date the previous content is restored. Without an end date the change is permanent. A field edited directly on the page or block while the update was live keeps that edit.
- An update that cannot be applied does not block the others: it is retried every minute, and the reason shows in the *Last Error* column and in the log.
- *Preview* opens the storefront with the scheduled content through a signed link, without changing the live content or the full page cache. It opens in a store view the page or block is assigned to, and the link works for 7 days.
- Two updates of the same content cannot overlap. Editing an active update goes live immediately; deleting it restores the previous content.
- All updates are listed under *Content > Elements > Planned Content Versions*.

Limitations:

- One update targets a single page or block (no campaigns grouping several contents).
- A field counts as changed when its text differs from the page or block: an editor that reformats the HTML on save makes the content field part of the update.
- Saving an active update puts its fields live again, over an edit made directly on the page or block.
- The preview opens in the first store view of a page or block assigned to several.

## Compatibility

Mage-OS 3.6 and 4.x, and Magento Open Source 2.4.9: all share the Magento 2.4.9 base (`magento/framework` ^103.0.9). PHP 8.3 to 8.5. CI runs the check-extension workflow on the currently supported Mage-OS versions (today Mage-OS 3.6.0 with PHP 8.4; Mage-OS 4.0 joins once the check-extension actions know it); developed on Mage-OS 4.0.0 with PHP 8.5. Page Builder is supported for the content field.

The module has no storefront template, checkout or catalog code; the preview is the page or block rendered by the theme in place.

| Target | Status |
|---|---|
| Luma | Not applicable: no storefront template |
| Hyvä Theme | Not applicable: no storefront template |
| Hyvä Checkout | Not applicable: nothing in the checkout |
| Loki Checkout | Not applicable: nothing in the checkout |
| Elasticsuite | Not applicable: nothing in search or catalog |

The preview's store view selection (`___store` parameter) was written from the core source and not run on a multi-store installation.

## Installation

```bash
composer config repositories.mageos-content-versions vcs https://github.com/mage-os-lab/module-content-versions
composer require mage-os/module-content-versions
bin/magento module:enable MageOS_ContentVersions
bin/magento setup:upgrade
```

## Configuration

No configuration. Admin pages and command:

- *Planned Versions* section on the CMS page and CMS block edit forms.
- *Content > Elements > Planned Content Versions*: all updates, with the last error of those that could not be applied.
- `bin/magento content-versions:run`: starts and ends the due updates now, as the cron job does. It prints the updates that failed and exits with an error code if there is any.

Viewing updates needs the *Planned Content Versions* ACL resource. Saving or deleting an update also needs the right to save that content: *Save Page* for pages, *Blocks* for blocks.

## Getting started

1. Open a CMS page and go to the *Planned Versions* section.

   ![Planned Versions section](docs/images/cms-page-planned-versions.png)

2. Click *Add Planned Version*, give it a name and a start date, optionally an end date, and change the title or content.

   ![Planned version form](docs/images/update-form.png)

3. Save, then use *Preview* in the list to see the storefront with that content. The link in the address bar can be sent for approval: it works for 7 days, and stops when the update is finished or deleted. *Preview* gives a new one.
4. At the start date the cron (every minute) puts the content live; at the end date the previous content comes back.

![Planned Content Versions grid](docs/images/grid.png)

## Troubleshooting

- **Nothing happens**: Magento cron must run (`bin/magento cron:run` every minute from the system crontab). Check `cron_schedule` for `mageos_content_versions_run`.
- **"You are not allowed to change this content"**: the admin role lacks *Save Page* or *Blocks*.
- **An update does not start or end**: see the *Last Error* column of *Content > Elements > Planned Content Versions*, or run `bin/magento content-versions:run`. The reason is also logged once in `var/log/system.log`. The update is retried every minute until it passes.
- **"The previous planned version of this content is still active"**: the update before this one could not be ended; fix its error first, this one then starts by itself.
- **A field was not restored at the end**: it was edited on the page or block while the update was live, and that edit is kept.
- **A preview link no longer works**: it is older than 7 days, was made before version 1.1, or its update is finished; click *Preview* again.

## More

- [CHANGELOG](CHANGELOG.md)
- [Security policy](SECURITY.md)
- [License](LICENSE.txt): Open Software License 3.0. This module contains no code from Adobe Commerce.
