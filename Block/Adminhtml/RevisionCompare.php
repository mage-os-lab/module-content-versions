<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Block\Adminhtml;

use MageOS\ContentVersions\Model\Entities;
use MageOS\ContentVersions\Model\Revisions;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Compares a revision (revision_id parameter) with the current content of its page or block.
 */
class RevisionCompare extends Template
{
    /**
     * @var string
     */
    protected $_template = 'MageOS_ContentVersions::revision_compare.phtml';

    /**
     * @param Context $context
     * @param Revisions $revisions
     * @param Entities $entities
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Revisions $revisions,
        private readonly Entities $entities,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The compared revision, null when it no longer exists.
     *
     * @return array{revision_id: int, entity_type: string, entity_id: int, data: array<string, mixed>,
     *     created_at: string}|null
     */
    public function getRevision(): ?array
    {
        return $this->revisions->get((int) $this->getRequest()->getParam('revision_id'));
    }

    /**
     * The fields that differ, as [revision value, current value]; null when the page or block was deleted.
     *
     * @param array{entity_type:string,entity_id:int,data:array<string,mixed>} $revision
     * @return array<string, array{0: mixed, 1: mixed}>|null
     */
    public function getDifferences(array $revision): ?array
    {
        try {
            $entity = $this->entities->load($revision['entity_type'], $revision['entity_id']);
        } catch (NoSuchEntityException $e) {
            return null;
        }
        return Revisions::differences($revision['data'], $entity->getData());
    }

    /**
     * Translated label of a content field.
     *
     * @param string $field
     */
    public function getFieldLabel(string $field): string
    {
        $labels = [
            'title' => __('Title'),
            'content_heading' => __('Content Heading'),
            'content' => __('Content'),
            'meta_title' => __('Meta Title'),
            'meta_keywords' => __('Meta Keywords'),
            'meta_description' => __('Meta Description'),
        ];
        return (string) ($labels[$field] ?? $field);
    }

    /**
     * URL to restore a revision.
     *
     * @param int $revisionId
     */
    public function getRestoreUrl(int $revisionId): string
    {
        return $this->getUrl('*/*/restore', ['revision_id' => $revisionId]);
    }

    /**
     * URL of the edit form of the revision's page or block, or of the versions grid without a revision.
     *
     * @param array{entity_type:string,entity_id:int}|null $revision
     */
    public function getBackUrl(?array $revision): string
    {
        return $revision
            ? $this->getUrl(
                'cms/' . $revision['entity_type'] . '/edit',
                [$revision['entity_type'] . '_id' => $revision['entity_id']]
            )
            : $this->getUrl('*/update/');
    }
}
