<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Controller\Adminhtml\Update;

use MageOS\ContentVersions\Model\Entities;
use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\Scheduler;
use MageOS\ContentVersions\Model\Update;
use MageOS\ContentVersions\Model\UpdateFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Puts the content of a finished planned version live again, for good.
 */
class Restore extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentVersions::update';

    /**
     * @param Context $context
     * @param UpdateFactory $updateFactory
     * @param UpdateResource $resource
     * @param Scheduler $scheduler
     */
    public function __construct(
        Context $context,
        private readonly UpdateFactory $updateFactory,
        private readonly UpdateResource $resource,
        private readonly Scheduler $scheduler
    ) {
        parent::__construct($context);
    }

    /**
     * Write the version's fields to its page or block now, then go back.
     */
    public function execute()
    {
        $update = $this->updateFactory->create();
        $this->resource->load($update, (int) $this->getRequest()->getParam('update_id'));
        $type = (string) $update->getEntityType();
        try {
            if (!$update->getId()) {
                throw new LocalizedException(__('This update no longer exists.'));
            }
            if ((int) $update->getStatus() !== Update::STATUS_FINISHED) {
                throw new LocalizedException(__('Only a finished version can be restored.'));
            }
            if (!isset(Entities::ACL[$type]) || !$this->_authorization->isAllowed(Entities::ACL[$type])) {
                throw new LocalizedException(__('You are not allowed to change this content.'));
            }
            $this->scheduler->restore($type, (int) $update->getEntityId(), $update->getStagedData());
            $this->messageManager->addSuccessMessage(__('The content of "%1" is live again.', $update->getName()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
