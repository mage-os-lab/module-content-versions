<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Integration;

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

/**
 * Tests that a planned version goes live at its start and the previous content comes back at its end.
 */
#[DbIsolation(true)]
class SchedulerTest extends TestCase
{
    /**
     * Start puts the staged content live, finish restores the content it replaced.
     */
    #[DataFixture(PageFixture::class, ['title' => 'Live title', 'content' => 'Live content'], 'page')]
    public function testUpdateGoesLiveThenRestoresTheLiveContent(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $pageId = (int) DataFixtureStorageManager::getStorage()->get('page')->getId();
        $update = $objectManager->get(UpdateFactory::class)->create();
        $update->setData([
            'entity_type' => 'page',
            'entity_id' => $pageId,
            'name' => 'Black Friday',
            'start_time' => gmdate('Y-m-d H:i:s', time() - 60),
            'end_time' => gmdate('Y-m-d H:i:s', time() + 3600),
            'data' => json_encode(['title' => 'Sale title', 'content' => 'Sale content']),
        ]);
        $objectManager->get(UpdateResource::class)->save($update);
        $scheduler = $objectManager->get(Scheduler::class);
        $pages = $objectManager->get(PageRepositoryInterface::class);

        $scheduler->start($update, gmdate('Y-m-d H:i:s'));
        $this->assertSame('Sale content', $pages->getById($pageId)->getContent());
        $this->assertSame(Update::STATUS_ACTIVE, (int) $update->getStatus());

        $scheduler->finish($update);
        $this->assertSame('Live content', $pages->getById($pageId)->getContent());
        $this->assertSame('Live title', $pages->getById($pageId)->getTitle());
        $this->assertSame(Update::STATUS_FINISHED, (int) $update->getStatus());
    }
}
