<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Unit;

use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\Revisions;
use MageOS\ContentVersions\Observer\DeleteRevisions;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests that deleting a CMS page or block deletes its revisions and planned versions.
 */
class DeleteRevisionsTest extends TestCase
{
    /**
     * The revisions and planned versions of the deleted page or block are deleted, by its type and ID.
     */
    public function testDeletedPageOrBlockLosesItsRevisionsAndPlannedVersions(): void
    {
        $page = (new ReflectionClass(Page::class))->newInstanceWithoutConstructor();
        $page->setData('page_id', 5);
        $page->setIdFieldName('page_id');
        $block = (new ReflectionClass(Block::class))->newInstanceWithoutConstructor();
        $block->setData('block_id', 7);
        $block->setIdFieldName('block_id');

        $revisions = $this->createMock(Revisions::class);
        $revisions->expects($this->exactly(2))->method('delete')->willReturnCallback(
            function (string $type, int $id): void {
                $this->assertContains([$type, $id], [['page', 5], ['block', 7]]);
            }
        );
        $updates = $this->createMock(UpdateResource::class);
        $updates->expects($this->exactly(2))->method('deleteByEntity')->willReturnCallback(
            function (string $type, int $id): void {
                $this->assertContains([$type, $id], [['page', 5], ['block', 7]]);
            }
        );
        $observer = new DeleteRevisions($revisions, $updates);
        $observer->execute(new Observer(['object' => $page]));
        $observer->execute(new Observer(['object' => $block]));
    }
}
