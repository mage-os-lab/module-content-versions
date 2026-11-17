<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Plugin;

use MageOS\ContentVersions\Model\Preview as PreviewModel;
use Magento\Cms\Model\ResourceModel\Block as BlockResource;
use Magento\Cms\Model\ResourceModel\Page as PageResource;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\View\Layout;

/**
 * Storefront preview of a scheduled update, plugged on the CMS page / block resources and the layout.
 */
class Preview
{
    /**
     * @param PreviewModel $preview
     */
    public function __construct(private readonly PreviewModel $preview)
    {
    }

    /**
     * Storefront loads of a CMS page or block get the previewed update's content.
     *
     * @param PageResource|BlockResource $subject
     * @param mixed $result
     * @param AbstractModel $object
     */
    public function afterLoad($subject, mixed $result, AbstractModel $object): mixed
    {
        $data = $object->getId()
            ? $this->preview->dataFor($subject instanceof PageResource ? 'page' : 'block', (int) $object->getId())
            : [];
        if ($data) {
            $object->addData($data);
        }
        return $result;
    }

    /**
     * A preview response must never be stored in the full page cache.
     *
     * @param Layout $subject
     * @param bool $result
     */
    public function afterIsCacheable(Layout $subject, bool $result): bool
    {
        return $result && !$this->preview->current();
    }
}
