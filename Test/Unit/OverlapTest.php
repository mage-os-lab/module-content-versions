<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Unit;

use MageOS\ContentVersions\Model\Scheduler;
use PHPUnit\Framework\TestCase;

/**
 * Tests the overlap rule between two updates of the same entity.
 */
class OverlapTest extends TestCase
{
    /**
     * Cases are [start, end, other start, other end, expected]; a null end is a permanent change.
     */
    public function testOverlaps(): void
    {
        $d = fn (int $day) => sprintf('2026-12-%02d 00:00:00', $day);
        $cases = [
            'separate periods' => [1, 5, 10, 15, false],
            'back to back' => [1, 5, 5, 10, false],
            'crossing periods' => [1, 10, 5, 15, true],
            'nested period' => [5, 7, 1, 10, true],
            'permanent inside a period' => [5, null, 1, 10, true],
            'permanent at the end of a period' => [10, null, 1, 10, false],
            'period over a permanent' => [1, 10, 5, null, true],
            'period starting with a permanent' => [5, 10, 5, null, true],
            'permanent before a period' => [1, null, 5, 10, false],
            'two permanents, same instant' => [5, null, 5, null, true],
            'two permanents, different days' => [5, null, 6, null, false],
        ];
        foreach ($cases as $label => [$start, $end, $otherStart, $otherEnd, $expected]) {
            $this->assertSame(
                $expected,
                Scheduler::overlaps(
                    $d($start),
                    $end ? $d($end) : null,
                    $d($otherStart),
                    $otherEnd ? $d($otherEnd) : null
                ),
                $label
            );
        }
    }
}
