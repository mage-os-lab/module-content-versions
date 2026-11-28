<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Unit;

use DateTimeImmutable;
use DateTimeZone;
use MageOS\ContentVersions\Model\Calendar;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the month grid of the planned versions calendar.
 */
class CalendarTest extends TestCase
{
    /**
     * The grid covers whole weeks, starting on the configured first day of the week.
     */
    public function testGridCoversWholeWeeksFromTheFirstDayOfTheWeek(): void
    {
        $december = new DateTimeImmutable('2026-12-15', new DateTimeZone('Europe/Paris'));

        [$start, $end] = Calendar::range($december, 1);
        $this->assertSame(['2026-11-30', '2027-01-04'], [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        [$start, $end] = Calendar::range($december, 0);
        $this->assertSame(['2026-11-29', '2027-01-03'], [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        $weeks = Calendar::weeks($december, 1, []);
        $this->assertCount(5, $weeks);
        $this->assertCount(7, $weeks[4]);
        $this->assertFalse($weeks[0][0]['in_month']);
        $this->assertSame(1, $weeks[0][1]['day']);
    }

    /**
     * A version shows on each day it is live in the admin time zone; one without an end on its start day only.
     */
    public function testVersionShowsOnTheDaysItIsLive(): void
    {
        $sale = new DataObject([
            'name' => 'Sale',
            // 2026-12-02 00:30 to 2026-12-04 00:00 in Paris (UTC+1): the 2nd and the 3rd.
            'start_time' => '2026-12-01 23:30:00',
            'end_time' => '2026-12-03 23:00:00',
        ]);
        $permanent = new DataObject(
            ['name' => 'New footer', 'start_time' => '2026-12-10 09:00:00', 'end_time' => null]
        );

        $days = [];
        $weeks = Calendar::weeks(new DateTimeImmutable('2026-12-01', new DateTimeZone('Europe/Paris')), 1, [
            $sale,
            $permanent,
        ]);
        foreach ($weeks as $week) {
            foreach ($week as $day) {
                foreach ($day['updates'] as $update) {
                    $days[$update->getData('name')][] = $day['date'];
                }
            }
        }

        $this->assertSame(
            ['Sale' => ['2026-12-02', '2026-12-03'], 'New footer' => ['2026-12-10']],
            $days
        );
    }
}
