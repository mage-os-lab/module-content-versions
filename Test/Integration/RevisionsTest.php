<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Integration;

use MageOS\ContentVersions\Model\Revisions;
use MageOS\ContentVersions\Model\Update;
use MageOS\ContentVersions\Setup\Patch\Data\DeleteOrphanRevisions;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Test\Fixture\Block as BlockFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Tests that each save of a CMS page keeps a revision, within the configured number.
 */
#[DbIsolation(true)]
class RevisionsTest extends TestCase
{
    /**
     * Saves keep the newest revisions only; a save that changes no content field adds none.
     */
    #[Config(Revisions::XML_PATH_LIMIT, '2')]
    #[DataFixture(PageFixture::class, ['title' => 'First', 'content' => 'Content'], 'page')]
    public function testSavesKeepTheNewestRevisions(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $pageId = (int) DataFixtureStorageManager::getStorage()->get('page')->getId();
        $pages = $objectManager->get(PageRepositoryInterface::class);
        foreach (['Second', 'Third', 'Third'] as $title) {
            $page = $pages->getById($pageId);
            $page->setTitle($title);
            $pages->save($page);
        }

        $revisions = $objectManager->get(Revisions::class)->list('page', $pageId);
        $this->assertSame(
            ['Third', 'Second'],
            array_map(static fn (array $revision): mixed => $revision['data']['title'], $revisions)
        );
    }

    /**
     * Deleting a page through the repository, or a block as the grid mass action does, deletes its revisions
     * and its planned versions, upcoming, active or finished.
     */
    #[Config(Revisions::XML_PATH_LIMIT, '20')]
    #[DataFixture(PageFixture::class, ['title' => 'Page', 'content' => 'Content'], 'page')]
    #[DataFixture(BlockFixture::class, ['title' => 'Block', 'content' => 'Content'], 'block')]
    #[DataFixture(PageFixture::class, ['title' => 'Kept', 'content' => 'Content'], 'kept')]
    public function testDeletingContentDeletesItsRevisionsAndPlannedVersions(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $storage = DataFixtureStorageManager::getStorage();
        $pageId = (int) $storage->get('page')->getId();
        $blockId = (int) $storage->get('block')->getId();
        $keptId = (int) $storage->get('kept')->getId();
        $revisions = $objectManager->get(Revisions::class);
        $pages = $objectManager->get(PageRepositoryInterface::class);
        foreach ([$pageId, $keptId] as $id) {
            $page = $pages->getById($id);
            $pages->save($page->setTitle('Saved'));
        }
        $block = $objectManager->get(BlockFactory::class)->create()->load($blockId);
        $block->setTitle('Saved')->save();
        $this->assertNotEmpty($revisions->list('page', $pageId));
        $this->assertNotEmpty($revisions->list('block', $blockId));
        $statuses = [Update::STATUS_SCHEDULED, Update::STATUS_ACTIVE, Update::STATUS_FINISHED];
        foreach ([['page', $pageId], ['block', $blockId], ['page', $keptId]] as [$type, $id]) {
            foreach ($statuses as $status) {
                $this->insertUpdate($type, $id, $status);
            }
        }

        $pages->deleteById($pageId);
        $objectManager->get(BlockFactory::class)->create()->load($blockId)->delete();

        $this->assertSame([], $revisions->list('page', $pageId));
        $this->assertSame([], $revisions->list('block', $blockId));
        $this->assertNotEmpty($revisions->list('page', $keptId), 'other pages keep their revisions');
        $this->assertSame(0, $this->countUpdates('page', $pageId));
        $this->assertSame(0, $this->countUpdates('block', $blockId));
        $this->assertSame(3, $this->countUpdates('page', $keptId), 'other pages keep their planned versions');
    }

    /**
     * The upgrade deletes the revisions and planned versions left by pages and blocks deleted before,
     * and keeps the others.
     */
    #[Config(Revisions::XML_PATH_LIMIT, '20')]
    #[DataFixture(PageFixture::class, ['title' => 'Kept', 'content' => 'Content'], 'kept')]
    public function testUpgradeDeletesOrphanRevisions(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $keptId = (int) DataFixtureStorageManager::getStorage()->get('kept')->getId();
        $revisions = $objectManager->get(Revisions::class);
        $revisions->record('page', $keptId, ['title' => 'Kept']);
        $resource = $objectManager->get(ResourceConnection::class);
        foreach (['page', 'block'] as $type) {
            $resource->getConnection()->insert(
                $resource->getTableName('mageos_versions_revision'),
                ['entity_type' => $type, 'entity_id' => 999999, 'data' => '{}']
            );
            $this->insertUpdate($type, 999999, Update::STATUS_SCHEDULED);
        }
        $this->insertUpdate('page', $keptId, Update::STATUS_SCHEDULED);

        $objectManager->create(DeleteOrphanRevisions::class)->apply();

        $this->assertSame([], $revisions->list('page', 999999));
        $this->assertSame([], $revisions->list('block', 999999));
        $this->assertNotEmpty($revisions->list('page', $keptId));
        $this->assertSame(0, $this->countUpdates('page', 999999));
        $this->assertSame(0, $this->countUpdates('block', 999999));
        $this->assertSame(1, $this->countUpdates('page', $keptId));
    }

    /**
     * Stores a planned version of a page or block with the given status.
     *
     * @param string $type
     * @param int $entityId
     * @param int $status
     */
    private function insertUpdate(string $type, int $entityId, int $status): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->insert($resource->getTableName('mageos_versions_update'), [
            'entity_type' => $type,
            'entity_id' => $entityId,
            'name' => 'Version',
            'start_time' => '2026-01-01 00:00:00',
            'status' => $status,
            'data' => '{"title":"Version"}',
        ]);
    }

    /**
     * How many planned versions a page or block has.
     *
     * @param string $type
     * @param int $entityId
     */
    private function countUpdates(string $type, int $entityId): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('mageos_versions_update'), 'COUNT(*)')
                ->where('entity_type = ?', $type)
                ->where('entity_id = ?', $entityId)
        );
    }
}
