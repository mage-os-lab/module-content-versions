<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

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
}
