<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * The CMS entities that can be staged, and the fields an update may replace.
 */
class Entities
{
    public const FIELDS = [
        'page' => ['title', 'content_heading', 'content', 'meta_title', 'meta_keywords', 'meta_description'],
        'block' => ['title', 'content'],
    ];

    /**
     * Core ACL resource needed to change each entity type, on top of MageOS_ContentVersions::update.
     */
    public const ACL = [
        'page' => 'Magento_Cms::save',
        'block' => 'Magento_Cms::block',
    ];

    /**
     * @param PageRepositoryInterface $pages
     * @param BlockRepositoryInterface $blocks
     */
    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly BlockRepositoryInterface $blocks
    ) {
    }

    /**
     * Load a page or block by type and ID.
     *
     * @param string $type
     * @param int $id
     * @return Page|Block
     * @throws NoSuchEntityException
     */
    public function load(string $type, int $id)
    {
        $entity = match ($type) {
            'page' => $this->pages->getById($id),
            'block' => $this->blocks->getById((string) $id),
            default => null,
        };
        if ($entity instanceof Page || $entity instanceof Block) {
            return $entity;
        }
        throw new NoSuchEntityException(__('Unknown content type "%1".', $type));
    }

    /**
     * Writes field values to the entity through its repository, so caches are invalidated as on any save.
     *
     * @param Page|Block $entity
     * @param array<string,mixed> $values
     */
    public function write($entity, array $values): void
    {
        $entity->addData($values);
        $entity instanceof Page ? $this->pages->save($entity) : $this->blocks->save($entity);
    }
}
