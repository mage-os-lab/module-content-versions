<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Observer;

use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\Revisions;
use Magento\Cms\Model\Page;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Deletes the revisions and planned versions of a CMS page or block with it, in the same transaction as the delete.
 *
 * Their tables serve pages and blocks, so no foreign key can cascade the delete.
 */
class DeleteRevisions implements ObserverInterface
{
    /**
     * @param Revisions $revisions
     * @param UpdateResource $updates
     */
    public function __construct(
        private readonly Revisions $revisions,
        private readonly UpdateResource $updates
    ) {
    }

    /**
     * Delete the revisions and planned versions of the deleted page or block.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getData('object');
        if ($object instanceof AbstractModel) {
            $type = $object instanceof Page ? 'page' : 'block';
            $this->revisions->delete($type, (int) $object->getId());
            $this->updates->deleteByEntity($type, (int) $object->getId());
        }
    }
}
