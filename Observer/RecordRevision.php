<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Observer;

use MageOS\ContentVersions\Model\Revisions;
use Magento\Cms\Model\Page;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Keeps a revision of a CMS page or block each time it is saved, in the same transaction as the save.
 */
class RecordRevision implements ObserverInterface
{
    /**
     * @param Revisions $revisions
     */
    public function __construct(private readonly Revisions $revisions)
    {
    }

    /**
     * Record the saved content of the page or block.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getData('object');
        if ($object instanceof AbstractModel) {
            $type = $object instanceof Page ? 'page' : 'block';
            $this->revisions->record($type, (int) $object->getId(), $object->getData());
        }
    }
}
