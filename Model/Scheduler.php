<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model;

use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\ResourceModel\Update\CollectionFactory;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Applies scheduled updates when they start and restores the previous content when they end.
 */
class Scheduler
{
    /**
     * @param CollectionFactory $updates
     * @param Entities $entities
     * @param UpdateResource $resource
     */
    public function __construct(
        private readonly CollectionFactory $updates,
        private readonly Entities $entities,
        private readonly UpdateResource $resource
    ) {
    }

    /**
     * Ends run before starts, so back-to-back updates restore the base content first.
     */
    public function run(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $ending = $this->updates->create()
            ->addFieldToFilter('status', ['eq' => Update::STATUS_ACTIVE])
            ->addFieldToFilter('end_time', ['lteq' => $now]);
        foreach ($ending as $update) {
            $this->finish($update);
        }
        $starting = $this->updates->create()
            ->addFieldToFilter('status', ['eq' => Update::STATUS_SCHEDULED])
            ->addFieldToFilter('start_time', ['lteq' => $now])
            ->setOrder('start_time', 'ASC');
        foreach ($starting as $update) {
            $this->start($update, $now);
        }
    }

    /**
     * Puts the staged content live and keeps the replaced values.
     *
     * An update without end is permanent; one whose whole window passed while cron was down
     * is just marked finished.
     *
     * @param Update $update
     * @param string $now
     */
    public function start(Update $update, string $now): void
    {
        $staged = $update->getStagedData();
        $missed = $update->getEndTime() && $update->getEndTime() <= $now;
        $entity = $missed ? null : $this->entity($update);
        if (!$entity) {
            $this->resource->save($update->setStatus(Update::STATUS_FINISHED));
            return;
        }
        $original = [];
        foreach (array_keys($staged) as $field) {
            $original[$field] = $entity->getData($field);
        }
        $this->entities->write($entity, $staged);
        $this->resource->save(
            $update->setData('original', json_encode($original))
                ->setStatus($update->getEndTime() ? Update::STATUS_ACTIVE : Update::STATUS_FINISHED)
        );
    }

    /**
     * Ends an active update: puts back the content it replaced.
     *
     * Shortcut: edits made to the staged fields directly on the page/block while the update was active are
     * overwritten here; keep a per-field "changed since start" check if editors need to do that.
     *
     * @param Update $update
     */
    public function finish(Update $update): void
    {
        $entity = $this->entity($update);
        if ($entity) {
            $this->entities->write($entity, $update->getOriginalData());
        }
        $this->resource->save($update->setStatus(Update::STATUS_FINISHED));
    }

    /**
     * An active update was edited: its new content goes live right away.
     *
     * @param Update $update
     */
    public function reapply(Update $update): void
    {
        $entity = $this->entity($update);
        if ($entity) {
            $this->entities->write($entity, $update->getStagedData());
        }
    }

    /**
     * Does another scheduled or active update of the same entity collide with this period?
     *
     * @param string $type
     * @param int $entityId
     * @param string $start
     * @param ?string $end
     * @param ?int $exceptId
     */
    public function hasConflict(string $type, int $entityId, string $start, ?string $end, ?int $exceptId): bool
    {
        $others = $this->updates->create()
            ->addFieldToFilter('entity_type', $type)
            ->addFieldToFilter('entity_id', ['eq' => $entityId])
            ->addFieldToFilter('status', ['neq' => Update::STATUS_FINISHED]);
        foreach ($others as $other) {
            if ((int) $other->getId() !== $exceptId
                && self::overlaps($start, $end, $other->getStartTime(), $other->getEndTime())
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Two updates of one entity must not be live together: the second one to end would restore stale content.
     *
     * Dates are "Y-m-d H:i:s" strings; a null end means a permanent change, which only occupies its start instant.
     *
     * @param string $start
     * @param ?string $end
     * @param string $otherStart
     * @param ?string $otherEnd
     */
    public static function overlaps(string $start, ?string $end, string $otherStart, ?string $otherEnd): bool
    {
        if ($end === null && $otherEnd === null) {
            return $start === $otherStart;
        }
        if ($end === null) {
            return $otherStart <= $start && $start < $otherEnd;
        }
        if ($otherEnd === null) {
            return $start <= $otherStart && $otherStart < $end;
        }
        return $start < $otherEnd && $otherStart < $end;
    }

    /**
     * The page or block of the update.
     *
     * @param Update $update
     * @return Page|Block|null null when the page/block was deleted
     */
    private function entity(Update $update)
    {
        try {
            return $this->entities->load((string) $update->getEntityType(), (int) $update->getEntityId());
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
