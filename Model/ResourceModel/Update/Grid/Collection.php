<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model\ResourceModel\Update\Grid;

use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Admin grid collection of updates, with the title of their page or block.
 */
class Collection extends SearchResult
{
    /**
     * Join the page or block title as "entity_title".
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()
            ->joinLeft(
                ['p' => $this->getTable('cms_page')],
                "main_table.entity_type = 'page' AND p.page_id = main_table.entity_id",
                []
            )
            ->joinLeft(
                ['b' => $this->getTable('cms_block')],
                "main_table.entity_type = 'block' AND b.block_id = main_table.entity_id",
                []
            )
            ->columns(['entity_title' => new Expression('COALESCE(p.title, b.title)')]);
    }
}
