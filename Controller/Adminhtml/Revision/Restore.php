<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Controller\Adminhtml\Revision;

use MageOS\ContentVersions\Model\Entities;
use MageOS\ContentVersions\Model\Revisions;
use MageOS\ContentVersions\Model\Scheduler;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Puts the content of a revision back on its page or block.
 */
class Restore extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentVersions::update';

    /**
     * @param Context $context
     * @param Revisions $revisions
     * @param Scheduler $scheduler
     */
    public function __construct(
        Context $context,
        private readonly Revisions $revisions,
        private readonly Scheduler $scheduler
    ) {
        parent::__construct($context);
    }

    /**
     * Write the revision's fields to the page or block, then go back to its edit form.
     */
    public function execute()
    {
        $revision = $this->revisions->get((int) $this->getRequest()->getParam('revision_id'));
        $redirect = $this->resultRedirectFactory->create();
        try {
            if (!$revision) {
                throw new LocalizedException(__('This revision no longer exists.'));
            }
            $type = $revision['entity_type'];
            if (!isset(Entities::ACL[$type]) || !$this->_authorization->isAllowed(Entities::ACL[$type])) {
                throw new LocalizedException(__('You are not allowed to change this content.'));
            }
            $this->scheduler->restore($type, $revision['entity_id'], $revision['data']);
            $this->messageManager->addSuccessMessage(__('You restored the revision.'));
            return $redirect->setPath('cms/' . $type . '/edit', [$type . '_id' => $revision['entity_id']]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setRefererOrBaseUrl();
        }
    }
}
