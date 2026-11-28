<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use RuntimeException;

/**
 * Resource model of the mageos_versions_update table.
 */
class Update extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('mageos_versions_update', 'update_id');
    }

    /**
     * Deletes the planned versions of a page or block, whatever their status.
     *
     * @param string $type
     * @param int $entityId
     */
    public function deleteByEntity(string $type, int $entityId): void
    {
        $connection = $this->getConnection();
        if (!$connection) {
            throw new RuntimeException('No database connection for the planned versions.');
        }
        $connection->delete(
            $this->getMainTable(),
            ['entity_type = ?' => $type, 'entity_id = ?' => $entityId]
        );
    }
}
