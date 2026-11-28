<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Block\Adminhtml;

use MageOS\ContentVersions\Model\Revisions;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * "Revisions" section of the CMS page / block edit form. Layout argument "entity_type": page or block.
 */
class EntityRevisions extends Template
{
    /**
     * @var string
     */
    protected $_template = 'MageOS_ContentVersions::entity_revisions.phtml';

    /**
     * @param Context $context
     * @param Revisions $revisions
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Revisions $revisions,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Nothing for admin roles without access to planned versions and revisions: their actions would be refused.
     */
    protected function _toHtml(): string
    {
        return $this->_authorization->isAllowed('MageOS_ContentVersions::update') ? parent::_toHtml() : '';
    }

    /**
     * Revisions of the page or block being edited, newest first; none when it is new.
     *
     * @return array<int, array{revision_id: int, entity_type: string, entity_id: int, data: array<string, mixed>,
     *     created_at: string}>
     */
    public function getRevisions(): array
    {
        $id = (int) $this->getRequest()->getParam($this->getData('entity_type') . '_id');
        return $id ? $this->revisions->list((string) $this->getData('entity_type'), $id) : [];
    }

    /**
     * URL of a revision admin action (compare, restore).
     *
     * @param string $action
     * @param int $revisionId
     */
    public function getActionUrl(string $action, int $revisionId): string
    {
        return $this->getUrl('mageos_versions/revision/' . $action, ['revision_id' => $revisionId]);
    }
}
