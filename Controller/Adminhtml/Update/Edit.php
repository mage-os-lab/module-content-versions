<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Controller\Adminhtml\Update;

use MageOS\ContentVersions\Model\Entities;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Edit an update (update_id), or schedule a new one for a page/block (type + id) or as a copy of another (from).
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentVersions::update';

    /**
     * Render the form, or go back to the grid when a new update has no valid type.
     */
    public function execute()
    {
        $isNew = !$this->getRequest()->getParam('update_id');
        $type = (string) $this->getRequest()->getParam('type');
        if ($isNew && !$this->getRequest()->getParam('from') && !isset(Entities::FIELDS[$type])) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if ($page instanceof Page) {
            $page->setActiveMenu('MageOS_ContentVersions::update');
        }
        $title = $isNew ? __('Add Planned Version') : __('Planned Content Version');
        $page->getConfig()->getTitle()->prepend((string) $title);
        return $page;
    }
}
