<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model;

use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\ResourceModel\Update\CollectionFactory;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Applies scheduled updates when they start and restores the previous content when they end.
 */
class Scheduler
{
    /**
     * @param CollectionFactory $updates
     * @param Entities $entities
     * @param UpdateResource $resource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CollectionFactory $updates,
        private readonly Entities $entities,
        private readonly UpdateResource $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Ends run before starts, so back-to-back updates restore the base content first.
     *
     * An update that cannot be applied does not stop the others: it is retried at the next run.
     *
     * @return string[] one line per update that could not be applied
     */
    public function run(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $failures = [];
        $ending = $this->updates->create()
            ->addFieldToFilter('status', ['eq' => Update::STATUS_ACTIVE])
            ->addFieldToFilter('end_time', ['lteq' => $now]);
        foreach ($ending as $update) {
            $failures[] = $this->attempt($update, fn () => $this->finish($update));
        }
        $starting = $this->updates->create()
            ->addFieldToFilter('status', ['eq' => Update::STATUS_SCHEDULED])
            ->addFieldToFilter('start_time', ['lteq' => $now])
            ->setOrder('start_time', 'ASC');
        foreach ($starting as $update) {
            $failures[] = $this->attempt($update, fn () => $this->start($update, $now));
        }
        return array_values(array_filter($failures));
    }

    /**
     * Puts the staged content live and keeps the replaced values.
     *
     * An update without end is permanent; one whose whole window passed while cron was down
     * is just marked finished.
     *
     * @param Update $update
     * @param string $now
     * @throws LocalizedException when the previous update of this content could not be ended yet
     */
    public function start(Update $update, string $now): void
    {
        $staged = $update->getStagedData();
        $missed = $update->getEndTime() && $update->getEndTime() <= $now;
        $entity = $missed ? null : $this->entity($update);
        if ($entity && $this->hasActive($update)) {
            // Starting now would keep that update's content as "the previous content" and restore it later.
            throw new LocalizedException(
                __('The previous planned version of this content is still active: it could not be ended.')
            );
        }
        $original = $entity ? array_intersect_key($entity->getData(), $staged) + array_fill_keys(
            array_keys($staged),
            null
        ) : [];
        $this->transaction(function () use ($update, $entity, $staged, $original): void {
            $claimed = $this->claim($update, Update::STATUS_SCHEDULED, [
                'status' => $entity && $update->getEndTime() ? Update::STATUS_ACTIVE : Update::STATUS_FINISHED,
                'original' => $entity ? json_encode($original) : null,
            ]);
            if ($claimed && $entity) {
                $this->entities->write($entity, $staged);
            }
        });
    }

    /**
     * Ends an active update: puts back the content it replaced.
     *
     * A field edited on the page / block while the update was live no longer holds the update's value:
     * that edit is kept.
     *
     * @param Update $update
     */
    public function finish(Update $update): void
    {
        $entity = $this->entity($update);
        $this->transaction(function () use ($update, $entity): void {
            if (!$this->claim($update, Update::STATUS_ACTIVE, ['status' => Update::STATUS_FINISHED]) || !$entity) {
                return;
            }
            // ponytail: compares with what the editor typed; an extension that rewrites the content on save
            // makes the field look edited and it is not restored. Store the saved values if that happens.
            $restore = self::restorable($update->getOriginalData(), $update->getStagedData(), $entity->getData());
            if ($restore) {
                $this->entities->write($entity, $restore);
            }
        });
    }

    /**
     * Saves an edited active update: its new content goes live right away.
     *
     * @param Update $update
     * @param array<string,mixed> $previous field values it put live until now
     */
    public function reapply(Update $update, array $previous): void
    {
        $entity = $this->entity($update);
        $values = [];
        if ($entity) {
            [$values, $original] = self::rebase(
                $update->getStagedData(),
                $previous,
                $update->getOriginalData(),
                $entity->getData()
            );
            $update->setData('original', json_encode($original));
        }
        $this->transaction(function () use ($update, $entity, $values): void {
            $this->resource->save($update);
            if ($entity && $values) {
                $this->entities->write($entity, $values);
            }
        });
    }

    /**
     * The posted fields whose value is not the one the content has now.
     *
     * @param array<string,mixed> $posted
     * @param array<string,mixed> $base
     * @return array<string, string>
     */
    public static function changed(array $posted, array $base): array
    {
        $changed = [];
        foreach ($posted as $field => $value) {
            if (self::differs($value, $base[$field] ?? null)) {
                $changed[$field] = (string) $value;
            }
        }
        return $changed;
    }

    /**
     * The replaced values to put back: only for the fields that still hold the update's value.
     *
     * @param array<string,mixed> $original
     * @param array<string,mixed> $staged
     * @param array<string,mixed> $live
     * @return array<string, mixed>
     */
    public static function restorable(array $original, array $staged, array $live): array
    {
        return array_filter(
            $original,
            static fn (string $field): bool => array_key_exists($field, $staged)
                && !self::differs($live[$field] ?? null, $staged[$field]),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * An active update was edited: the values to write, and the replaced values to keep from now on.
     *
     * A field it starts to change is remembered; a field it stops changing goes back to what it replaced,
     * unless it was edited on the page / block in the meantime.
     *
     * @param array<string,mixed> $staged
     * @param array<string,mixed> $previous
     * @param array<string,mixed> $original
     * @param array<string,mixed> $live
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function rebase(array $staged, array $previous, array $original, array $live): array
    {
        $values = $staged;
        foreach (array_keys($staged) as $field) {
            if (!array_key_exists($field, $original)) {
                $original[$field] = $live[$field] ?? null;
            }
        }
        $dropped = array_diff_key($previous, $staged);
        $values += self::restorable(array_intersect_key($original, $dropped), $dropped, $live);
        return [$values, array_diff_key($original, $dropped)];
    }

    /**
     * Compares two field values as text; line endings alone are not a difference (forms post CRLF).
     *
     * @param mixed $a
     * @param mixed $b
     */
    public static function differs(mixed $a, mixed $b): bool
    {
        return str_replace("\r\n", "\n", (string) $a) !== str_replace("\r\n", "\n", (string) $b);
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

    /**
     * Is another update of the same page / block live? Updates cannot overlap, so it is one that failed to end.
     *
     * @param Update $update
     */
    private function hasActive(Update $update): bool
    {
        return $this->updates->create()
            ->addFieldToFilter('entity_type', $update->getEntityType())
            ->addFieldToFilter('entity_id', ['eq' => (int) $update->getEntityId()])
            ->addFieldToFilter('status', ['eq' => Update::STATUS_ACTIVE])
            ->addFieldToFilter('update_id', ['neq' => (int) $update->getId()])
            ->getSize() > 0;
    }

    /**
     * Moves the update out of the given status in one statement, so two runs never apply it twice.
     *
     * @param Update $update
     * @param int $from
     * @param array<string,mixed> $values
     * @return bool false when another run already did it
     */
    private function claim(Update $update, int $from, array $values): bool
    {
        $values['error'] = null;
        $claimed = $this->connection()->update(
            $this->resource->getMainTable(),
            $values,
            ['update_id = ?' => (int) $update->getId(), 'status = ?' => $from]
        ) === 1;
        if ($claimed) {
            $update->addData($values);
        }
        return $claimed;
    }

    /**
     * The connection of the updates table.
     */
    private function connection(): AdapterInterface
    {
        $connection = $this->resource->getConnection();
        if (!$connection) {
            throw new RuntimeException('No database connection for the planned versions.');
        }
        return $connection;
    }

    /**
     * The update's row and the page / block change together or not at all.
     *
     * @param callable $work
     * @throws Throwable
     */
    private function transaction(callable $work): void
    {
        $this->resource->beginTransaction();
        try {
            $work();
            $this->resource->commit();
        } catch (Throwable $e) {
            $this->resource->rollBack();
            throw $e;
        }
    }

    /**
     * Applies one update; a failure is logged and kept on the update once, not at every run.
     *
     * @param Update $update
     * @param callable $apply
     * @return ?string the failure, null when applied
     */
    private function attempt(Update $update, callable $apply): ?string
    {
        try {
            $apply();
            return null;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            if ($error !== $update->getData('error')) {
                $this->logger->error(
                    sprintf('Planned content version #%d could not be applied: %s', $update->getId(), $error),
                    ['exception' => $e]
                );
                $this->connection()->update(
                    $this->resource->getMainTable(),
                    ['error' => $error],
                    ['update_id = ?' => (int) $update->getId()]
                );
            }
            return sprintf('#%d "%s": %s', $update->getId(), $update->getName(), $error);
        }
    }
}
