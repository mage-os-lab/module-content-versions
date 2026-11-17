<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Controller\Adminhtml\Update;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Admin grid of all planned content versions.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentVersions::update';

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if ($page instanceof Page) {
            $page->setActiveMenu('MageOS_ContentVersions::update');
        }
        $page->getConfig()->getTitle()->prepend((string) __('Planned Content Versions'));
        return $page;
    }
}
