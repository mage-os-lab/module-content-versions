<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Controller\Adminhtml\Update;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use MageOS\ContentVersions\Model\Entities;
use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\Scheduler;
use MageOS\ContentVersions\Model\Update;
use MageOS\ContentVersions\Model\UpdateFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;

/**
 * Saves an update with the fields it changes; an active one goes live again right away.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentVersions::update';

    /**
     * @param Context $context
     * @param UpdateFactory $updateFactory
     * @param Entities $entities
     * @param Scheduler $scheduler
     * @param DataPersistorInterface $dataPersistor
     * @param UpdateResource $resource
     * @param HttpRequest $request
     */
    public function __construct(
        Context $context,
        private readonly UpdateFactory $updateFactory,
        private readonly Entities $entities,
        private readonly Scheduler $scheduler,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly UpdateResource $resource,
        private readonly HttpRequest $request
    ) {
        parent::__construct($context);
    }

    /**
     * Save the posted update; on a validation error, keep what the editor typed and go back to the form.
     */
    public function execute()
    {
        $post = (array) $this->request->getPostValue();
        $redirect = $this->resultRedirectFactory->create();
        $update = $this->updateFactory->create();
        if (!empty($post['update_id'])) {
            $this->resource->load($update, (int) $post['update_id']);
        }
        try {
            $previous = $update->getStagedData();
            $this->fill($update, $post);
            if ((int) $update->getStatus() === Update::STATUS_ACTIVE) {
                $this->scheduler->reapply($update, $previous);
            } else {
                $this->resource->save($update);
            }
            $this->messageManager->addSuccessMessage(__('You saved the scheduled update.'));
            return $redirect->setPath(
                'cms/' . $update->getEntityType() . '/edit',
                [$update->getEntityType() . '_id' => $update->getEntityId()]
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set('mageos_versions_update', $post);
            return $redirect->setPath('*/*/edit', array_filter([
                'update_id' => $update->getId(),
                'type' => $post['entity_type'] ?? null,
                'id' => $post['entity_id'] ?? null,
            ]));
        }
    }

    /**
     * Validate the posted data and copy it to the update. The start of an active update can no longer move.
     *
     * @param Update $update
     * @param array<string,mixed> $post
     * @throws LocalizedException when the posted data is not a valid update
     */
    private function fill(Update $update, array $post): void
    {
        if (!empty($post['update_id']) && !$update->getId()) {
            throw new LocalizedException(__('This update no longer exists.'));
        }
        if ((int) $update->getStatus() === Update::STATUS_FINISHED) {
            throw new LocalizedException(__('A finished update cannot be changed.'));
        }
        if (!$update->getId()) {
            $update->setEntityType((string) ($post['entity_type'] ?? ''))->setEntityId((int) ($post['entity_id'] ?? 0));
        }
        $type = (string) $update->getEntityType();
        $entity = $this->entities->load($type, (int) $update->getEntityId());
        if (!$this->_authorization->isAllowed(Entities::ACL[$type])) {
            throw new LocalizedException(__('You are not allowed to change this content.'));
        }

        $start = (int) $update->getStatus() === Update::STATUS_ACTIVE
            ? (string) $update->getStartTime()
            : $this->date($post['start_time'] ?? null);
        $end = $this->date($post['end_time'] ?? null);
        $name = trim((string) ($post['name'] ?? ''));
        if ($name === '' || $start === null) {
            throw new LocalizedException(__('A name and a start date are required.'));
        }
        if ($end !== null && $end <= $start) {
            throw new LocalizedException(__('The end date must be after the start date.'));
        }
        $id = $update->getId() ? (int) $update->getId() : null;
        if ($this->scheduler->hasConflict($type, (int) $update->getEntityId(), $start, $end, $id)) {
            throw new LocalizedException(__('Another update of this content is scheduled during this period.'));
        }

        // Only what the editor changed is stored, so later edits of the other fields are not replaced. An active
        // update is compared with the content it replaced, as the live content already holds its values.
        $staged = Scheduler::changed(
            array_filter(array_intersect_key($post, array_flip(Entities::FIELDS[$type])), 'is_scalar'),
            $update->getOriginalData() + $entity->getData()
        );
        if (!$staged) {
            throw new LocalizedException(__('Change at least one field of the content.'));
        }
        $update->setName($name)->setStartTime($start)->setEndTime($end)->setData('data', json_encode($staged));
    }

    /**
     * The form posts UTC ISO 8601 date-times; the table stores UTC "Y-m-d H:i:s".
     *
     * A value without an offset is read as UTC, whatever PHP's default time zone.
     *
     * @param mixed $value
     */
    private function date(mixed $value): ?string
    {
        $utc = new DateTimeZone('UTC');
        try {
            $date = $value ? new DateTimeImmutable((string) $value, $utc) : null;
        } catch (Exception) {
            $date = null;
        }
        return $date?->setTimezone($utc)->format('Y-m-d H:i:s');
    }
}
