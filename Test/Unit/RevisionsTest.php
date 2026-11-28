<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Unit;

use MageOS\ContentVersions\Model\Revisions;
use PHPUnit\Framework\TestCase;

/**
 * Tests what a revision keeps and how it compares with the current content.
 */
class RevisionsTest extends TestCase
{
    /**
     * A revision keeps the content fields of its type only, an empty field included.
     */
    public function testRevisionKeepsTheContentFieldsOfItsType(): void
    {
        $this->assertSame(
            ['title' => 'Footer', 'content' => null],
            Revisions::snapshot('block', ['title' => 'Footer', 'identifier' => 'footer', 'is_active' => 1])
        );
        $this->assertSame([], Revisions::snapshot('product', ['title' => 'Footer']));
    }

    /**
     * The comparison lists the changed fields only, with the revision value first; line endings are no change.
     */
    public function testComparisonListsTheChangedFieldsOnly(): void
    {
        $this->assertSame(
            ['title' => ['Home', 'Sale'], 'meta_title' => ['Welcome', null]],
            Revisions::differences(
                ['title' => 'Home', 'content' => "A\r\nB", 'meta_title' => 'Welcome', 'content_heading' => null],
                ['title' => 'Sale', 'content' => "A\nB", 'content_heading' => '']
            )
        );
    }

    /**
     * A revision identical to the current content has nothing to compare.
     */
    public function testSameContentHasNoDifference(): void
    {
        $this->assertSame([], Revisions::differences(['title' => 'Home'], ['title' => 'Home', 'is_active' => 1]));
    }
}
