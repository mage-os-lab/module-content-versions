<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Unit;

use MageOS\ContentVersions\Model\Scheduler;
use PHPUnit\Framework\TestCase;

/**
 * Tests which fields a planned version stores, replaces and restores.
 */
class ChangedFieldsTest extends TestCase
{
    /**
     * Only the fields the editor changed are stored; line endings alone are not a change.
     */
    public function testOnlyChangedFieldsAreStored(): void
    {
        $this->assertSame(
            ['title' => 'Sale'],
            Scheduler::changed(
                ['title' => 'Sale', 'content' => "Line 1\r\nLine 2", 'meta_title' => ''],
                ['title' => 'Home', 'content' => "Line 1\nLine 2", 'meta_title' => null, 'identifier' => 'home']
            )
        );
    }

    /**
     * At the end, a field edited on the page while the version was live keeps that edit.
     */
    public function testFieldsEditedWhileLiveAreNotRestored(): void
    {
        $this->assertSame(
            ['title' => 'Home'],
            Scheduler::restorable(
                ['title' => 'Home', 'content' => 'Old content'],
                ['title' => 'Sale', 'content' => 'Sale content'],
                ['title' => 'Sale', 'content' => 'Sale content, typo fixed by hand']
            )
        );
    }

    /**
     * A version planned with 1.0.0 stored every field: those it did not change are restored to the same value.
     */
    public function testVersionThatStoredEveryFieldRestoresWhatItReplaced(): void
    {
        $this->assertSame(
            ['title' => 'Home', 'content' => 'Same'],
            Scheduler::restorable(
                ['title' => 'Home', 'content' => 'Same'],
                ['title' => 'Sale', 'content' => 'Same'],
                ['title' => 'Sale', 'content' => 'Same']
            )
        );
    }

    /**
     * Editing a live version: a field it starts to change is remembered, one it stops changing goes back.
     */
    public function testEditingALiveVersionKeepsTheReplacedValues(): void
    {
        [$values, $original] = Scheduler::rebase(
            ['content' => 'Sale content'],
            ['title' => 'Sale'],
            ['title' => 'Home'],
            ['title' => 'Sale', 'content' => 'Old content']
        );
        $this->assertSame(['content' => 'Sale content', 'title' => 'Home'], $values);
        $this->assertSame(['content' => 'Old content'], $original);
    }

    /**
     * Editing a live version: a field it stops changing keeps an edit made on the page in the meantime.
     */
    public function testEditingALiveVersionKeepsAManualEdit(): void
    {
        [$values, $original] = Scheduler::rebase(
            [],
            ['title' => 'Sale'],
            ['title' => 'Home'],
            ['title' => 'Edited by hand']
        );
        $this->assertSame([], $values);
        $this->assertSame([], $original);
    }

    /**
     * A version restored while another one is live, with the same content, stays live when that one ends.
     */
    public function testRestoreDuringALiveVersionWithTheSameContentStaysLive(): void
    {
        $staged = ['content' => '<p>A-PROMO</p>'];
        $restored = ['title' => 'Promo', 'content' => '<p>A-PROMO</p>'];
        $original = Scheduler::kept(['content' => '<p>Before</p>'], $restored);
        $this->assertSame(['content' => '<p>A-PROMO</p>'], $original);
        $this->assertSame(
            ['content' => '<p>A-PROMO</p>'],
            Scheduler::restorable($original, $staged, $restored)
        );
    }
}
