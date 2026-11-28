<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Controller\Adminhtml\Revision;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Compares a revision (revision_id) with the current content of its page or block.
 */
class Compare extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentVersions::update';

    /**
     * Render the comparison page.
     */
    public function execute()
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if ($page instanceof Page) {
            $page->setActiveMenu('MageOS_ContentVersions::update');
        }
        $page->getConfig()->getTitle()->prepend((string) __('Compare Revision'));
        return $page;
    }
}
