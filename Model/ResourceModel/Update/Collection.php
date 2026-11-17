<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model\ResourceModel\Update;

use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\Update;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Collection of scheduled updates.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'update_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Update::class, UpdateResource::class);
    }
}
