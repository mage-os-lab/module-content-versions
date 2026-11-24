<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Integration;

use MageOS\ContentVersions\Model\Entities;
use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\Scheduler;
use MageOS\ContentVersions\Model\Update;
use MageOS\ContentVersions\Model\UpdateFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests what a run leaves behind when a planned version cannot be applied. No DB isolation: the rollback is real.
 */
#[DbIsolation(false)]
class SchedulerFailureTest extends TestCase
{
    /**
     * @var Update[]
     */
    private array $updates = [];

    /**
     * Deletes the updates the test created.
     */
    protected function tearDown(): void
    {
        foreach ($this->updates as $update) {
            Bootstrap::getObjectManager()->get(UpdateResource::class)->delete($update);
        }
    }

    /**
     * A start that fails after the content was written changes nothing, does not stop the other versions,
     * and the retry still restores the real previous content at the end.
     */
    #[DataFixture(PageFixture::class, ['title' => 'Live title', 'content' => 'Live content'], 'broken')]
    #[DataFixture(PageFixture::class, ['title' => 'Other title', 'content' => 'Other content'], 'healthy')]
    public function testFailedStartIsRolledBackAndDoesNotStopTheOthers(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $brokenId = (int) DataFixtureStorageManager::getStorage()->get('broken')->getId();
        $healthyId = (int) DataFixtureStorageManager::getStorage()->get('healthy')->getId();
        $broken = $this->plan($brokenId, time() - 120);
        $healthy = $this->plan($healthyId, time() - 60);
        $pages = $objectManager->get(PageRepositoryInterface::class);
        $resource = $objectManager->get(UpdateResource::class);

        $entities = $objectManager->get(Entities::class);
        $failing = $this->createStub(Entities::class);
        $failing->method('load')->willReturnCallback($entities->load(...));
        $failing->method('write')->willReturnCallback(
            function ($entity, array $values) use ($entities, $brokenId): void {
                $entities->write($entity, $values);
                if ((int) $entity->getId() === $brokenId) {
                    throw new RuntimeException('Connection lost');
                }
            }
        );
        $failures = $objectManager->create(Scheduler::class, ['entities' => $failing])->run();

        $this->assertCount(1, $failures);
        $this->assertStringContainsString('Connection lost', $failures[0]);
        $this->assertSame('Live content', $pages->getById($brokenId)->getContent(), 'content write rolled back');
        $resource->load($broken, $broken->getId());
        $this->assertSame(Update::STATUS_SCHEDULED, (int) $broken->getStatus());
        $this->assertSame([], $broken->getOriginalData());
        $this->assertSame('Connection lost', $broken->getData('error'));
        $this->assertSame('Sale content', $pages->getById($healthyId)->getContent(), 'the other version started');
        $resource->load($healthy, $healthy->getId());
        $this->assertSame(Update::STATUS_ACTIVE, (int) $healthy->getStatus());

        $scheduler = $objectManager->create(Scheduler::class);
        $this->assertSame([], $scheduler->run());
        $this->assertSame('Sale content', $pages->getById($brokenId)->getContent());
        $resource->load($broken, $broken->getId());
        $this->assertNull($broken->getData('error'));
        $scheduler->finish($broken);
        $this->assertSame('Live content', $pages->getById($brokenId)->getContent(), 'the real content is back');
    }

    /**
     * Plans a version of a page, due since the given time.
     *
     * @param int $pageId
     * @param int $start
     */
    private function plan(int $pageId, int $start): Update
    {
        $update = Bootstrap::getObjectManager()->get(UpdateFactory::class)->create();
        $update->setData([
            'entity_type' => 'page',
            'entity_id' => $pageId,
            'name' => 'Black Friday',
            'start_time' => gmdate('Y-m-d H:i:s', $start),
            'end_time' => gmdate('Y-m-d H:i:s', time() + 3600),
            'data' => json_encode(['content' => 'Sale content']),
        ]);
        Bootstrap::getObjectManager()->get(UpdateResource::class)->save($update);
        return $this->updates[] = $update;
    }
}
