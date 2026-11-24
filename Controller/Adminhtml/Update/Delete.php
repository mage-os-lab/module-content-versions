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
 * Deletes an update; an active one first puts the previous content back.
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentVersions::update';

    /**
     * @param Context $context
     * @param UpdateFactory $updateFactory
     * @param Scheduler $scheduler
     * @param UpdateResource $resource
     */
    public function __construct(
        Context $context,
        private readonly UpdateFactory $updateFactory,
        private readonly Scheduler $scheduler,
        private readonly UpdateResource $resource
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $update = $this->updateFactory->create();
        $this->resource->load($update, (int) $this->getRequest()->getParam('update_id'));
        $acl = Entities::ACL[(string) $update->getEntityType()] ?? null;
        if ($update->getId() && (!$acl || !$this->_authorization->isAllowed($acl))) {
            $this->messageManager->addErrorMessage(__('You are not allowed to change this content.'));
        } elseif ($update->getId()) {
            try {
                if ((int) $update->getStatus() === Update::STATUS_ACTIVE) {
                    $this->scheduler->finish($update);
                }
                $this->resource->delete($update);
                $this->messageManager->addSuccessMessage(__('You deleted the scheduled update.'));
            } catch (LocalizedException $e) {
                // The previous content could not be put back: the update stays, with its content live.
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
