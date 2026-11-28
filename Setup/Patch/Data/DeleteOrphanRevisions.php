<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Deletes the revisions and planned versions of CMS pages and blocks deleted before they were deleted with them.
 */
class DeleteOrphanRevisions implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $setup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $setup
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $connection = $this->setup->getConnection();
        foreach (['mageos_versions_revision', 'mageos_versions_update'] as $orphans) {
            foreach (['page' => 'cms_page', 'block' => 'cms_block'] as $type => $table) {
                $connection->delete($this->setup->getTable($orphans), [
                    'entity_type = ?' => $type,
                    'entity_id NOT IN ?' => $connection->select()->from($this->setup->getTable($table), $type . '_id'),
                ]);
            }
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
