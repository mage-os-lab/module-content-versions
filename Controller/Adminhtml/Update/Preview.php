<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Controller\Adminhtml\Update;

use MageOS\ContentVersions\Model\Preview as PreviewLink;
use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\UpdateFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Opens the storefront preview of an update, with a link signed at that moment.
 */
class Preview extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentVersions::update';

    /**
     * @param Context $context
     * @param UpdateFactory $updateFactory
     * @param UpdateResource $resource
     * @param PreviewLink $preview
     */
    public function __construct(
        Context $context,
        private readonly UpdateFactory $updateFactory,
        private readonly UpdateResource $resource,
        private readonly PreviewLink $preview
    ) {
        parent::__construct($context);
    }

    /**
     * Redirect to the signed storefront URL, or back with an error when the update is gone.
     */
    public function execute()
    {
        $update = $this->updateFactory->create();
        $this->resource->load($update, (int) $this->getRequest()->getParam('update_id'));
        $redirect = $this->resultRedirectFactory->create();
        if (!$update->getId()) {
            $this->messageManager->addErrorMessage(__('This update no longer exists.'));
            return $redirect->setRefererOrBaseUrl();
        }
        return $redirect->setUrl($this->preview->url($update));
    }
}
