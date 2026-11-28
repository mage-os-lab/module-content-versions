<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

/**
 * Revisions of CMS pages and blocks: the content after each save, the last ones kept per page or block.
 */
class Revisions
{
    /**
     * How many revisions are kept per page or block; 0 records none.
     */
    public const XML_PATH_LIMIT = 'cms/mageos_content_versions/revisions';

    private const TABLE = 'mageos_versions_revision';

    /**
     * @param ResourceConnection $resource
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Keeps the content just saved, unless it is the same as the last revision, then deletes the oldest ones.
     *
     * @param string $type
     * @param int $entityId
     * @param array<string,mixed> $entityData
     */
    public function record(string $type, int $entityId, array $entityData): void
    {
        $limit = (int) $this->scopeConfig->getValue(self::XML_PATH_LIMIT);
        if ($limit <= 0 || !isset(Entities::FIELDS[$type]) || !$entityId) {
            return;
        }
        $snapshot = self::snapshot($type, $entityData);
        $last = $this->list($type, $entityId, 1)[0] ?? null;
        if ($last && !self::differences($last['data'], $snapshot)) {
            return;
        }
        $connection = $this->connection();
        $table = $this->resource->getTableName(self::TABLE);
        $connection->insert($table, [
            'entity_type' => $type,
            'entity_id' => $entityId,
            'data' => json_encode($snapshot),
        ]);
        $oldestKept = $connection->fetchOne(
            $this->select($type, $entityId)->reset(Select::COLUMNS)->columns('revision_id')->limit(1, $limit - 1)
        );
        if ($oldestKept) {
            $connection->delete($table, [
                'entity_type = ?' => $type,
                'entity_id = ?' => $entityId,
                'revision_id < ?' => (int) $oldestKept,
            ]);
        }
    }

    /**
     * Deletes the revisions of a page or block.
     *
     * @param string $type
     * @param int $entityId
     */
    public function delete(string $type, int $entityId): void
    {
        $this->connection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['entity_type = ?' => $type, 'entity_id = ?' => $entityId]
        );
    }

    /**
     * Revisions of a page or block, newest first.
     *
     * @param string $type
     * @param int $entityId
     * @param int $limit 0 for all
     * @return array<int, array{revision_id: int, entity_type: string, entity_id: int, data: array<string, mixed>,
     *     created_at: string}>
     */
    public function list(string $type, int $entityId, int $limit = 0): array
    {
        $select = $this->select($type, $entityId);
        if ($limit) {
            $select->limit($limit);
        }
        return array_map([$this, 'row'], $this->connection()->fetchAll($select));
    }

    /**
     * One revision, null when it no longer exists.
     *
     * @param int $revisionId
     * @return array{revision_id: int, entity_type: string, entity_id: int, data: array<string, mixed>,
     *     created_at: string}|null
     */
    public function get(int $revisionId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE))
                ->where('revision_id = ?', $revisionId)
        );
        return $row ? $this->row($row) : null;
    }

    /**
     * The fields a revision keeps, from the data of a page or block.
     *
     * @param string $type
     * @param array<string,mixed> $entityData
     * @return array<string, mixed>
     */
    public static function snapshot(string $type, array $entityData): array
    {
        $snapshot = [];
        foreach (Entities::FIELDS[$type] ?? [] as $field) {
            $snapshot[$field] = $entityData[$field] ?? null;
        }
        return $snapshot;
    }

    /**
     * The fields whose revision value differs from the current one, as [revision value, current value].
     *
     * @param array<string,mixed> $revision
     * @param array<string,mixed> $current
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function differences(array $revision, array $current): array
    {
        $differences = [];
        foreach ($revision as $field => $value) {
            if (Scheduler::differs($value, $current[$field] ?? null)) {
                $differences[$field] = [$value, $current[$field] ?? null];
            }
        }
        return $differences;
    }

    /**
     * Revisions of a page or block, newest first.
     *
     * @param string $type
     * @param int $entityId
     */
    private function select(string $type, int $entityId): Select
    {
        return $this->connection()->select()
            ->from($this->resource->getTableName(self::TABLE))
            ->where('entity_type = ?', $type)
            ->where('entity_id = ?', $entityId)
            ->order('revision_id DESC');
    }

    /**
     * A table row with its data decoded.
     *
     * @param array<string,mixed> $row
     * @return array{revision_id: int, entity_type: string, entity_id: int, data: array<string, mixed>,
     *     created_at: string}
     */
    private function row(array $row): array
    {
        return [
            'revision_id' => (int) $row['revision_id'],
            'entity_type' => (string) $row['entity_type'],
            'entity_id' => (int) $row['entity_id'],
            'data' => json_decode((string) $row['data'], true) ?: [],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * The connection of the revisions table.
     */
    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
