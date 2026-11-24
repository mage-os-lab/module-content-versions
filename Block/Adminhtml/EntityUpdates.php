<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Block\Adminhtml;

use MageOS\ContentVersions\Model\ResourceModel\Update\CollectionFactory;
use MageOS\ContentVersions\Model\Source\Status;
use MageOS\ContentVersions\Model\Update;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * "Planned Versions" section of the CMS page / block edit form. Layout argument "entity_type": page or block.
 */
class EntityUpdates extends Template
{
    /**
     * @var string
     */
    protected $_template = 'MageOS_ContentVersions::entity_updates.phtml';

    /**
     * @param Context $context
     * @param CollectionFactory $updates
     * @param Status $status
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $updates,
        private readonly Status $status,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * ID of the page or block being edited, 0 when it is new.
     */
    public function getEntityId(): int
    {
        return (int) $this->getRequest()->getParam($this->getData('entity_type') . '_id');
    }

    /**
     * Updates of this page or block, by start date.
     *
     * @return Update[]
     */
    public function getUpdates(): array
    {
        $items = $this->updates->create()
            ->addFieldToFilter('entity_type', $this->getData('entity_type'))
            ->addFieldToFilter('entity_id', ['eq' => $this->getEntityId()])
            ->setOrder('start_time', 'ASC')
            ->getItems();
        return array_filter($items, static fn ($item): bool => $item instanceof Update);
    }

    /**
     * URL to schedule a new update of this page or block.
     */
    public function getNewUrl(): string
    {
        return $this->getUrl(
            'mageos_versions/update/edit',
            ['type' => $this->getData('entity_type'), 'id' => $this->getEntityId()]
        );
    }

    /**
     * URL of an update admin action (edit, preview, delete).
     *
     * @param string $action
     * @param Update $update
     */
    public function getActionUrl(string $action, Update $update): string
    {
        return $this->getUrl('mageos_versions/update/' . $action, ['update_id' => $update->getId()]);
    }

    /**
     * Translated status of the update.
     *
     * @param Update $update
     */
    public function getStatusLabel(Update $update): string
    {
        foreach ($this->status->toOptionArray() as $option) {
            if ($option['value'] === (int) $update->getStatus()) {
                return (string) $option['label'];
            }
        }
        return '';
    }
}
