<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Plugin;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;

/**
 * Hides the Planned Versions and Revisions sections of the CMS page / block forms from roles without access to them.
 */
class HideSections
{
    /**
     * @param AuthorizationInterface $authorization
     */
    public function __construct(private readonly AuthorizationInterface $authorization)
    {
    }

    /**
     * Disable the module's fieldsets when the admin user lacks MageOS_ContentVersions::update.
     *
     * @param DataProviderInterface $subject
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public function afterGetMeta(DataProviderInterface $subject, array $meta): array
    {
        if ($this->authorization->isAllowed('MageOS_ContentVersions::update')) {
            return $meta;
        }
        foreach (['scheduled_changes', 'mageos_versions_revisions'] as $fieldset) {
            $meta[$fieldset]['arguments']['data']['config']['componentDisabled'] = true;
        }
        return $meta;
    }
}
