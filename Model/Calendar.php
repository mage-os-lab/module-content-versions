<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model;

use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\DataObject;

/**
 * Month grid of the planned versions, in the admin time zone.
 */
class Calendar
{
    /**
     * First day shown by the grid of a month, and the day after the last one, at local midnight.
     *
     * @param DateTimeImmutable $month any day of the month, in the admin time zone
     * @param int $firstDay first day of the week, 0 = Sunday
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public static function range(DateTimeImmutable $month, int $firstDay): array
    {
        $first = $month->modify('first day of this month')->setTime(0, 0);
        $start = $first->modify('-' . (((int) $first->format('w') - $firstDay + 7) % 7) . ' days');
        $last = $first->modify('last day of this month');
        $end = $last->modify('+' . (7 - ((int) $last->format('w') - $firstDay + 7) % 7) . ' days');
        return [$start, $end];
    }

    /**
     * Weeks of the month grid; each day lists the versions live during that day.
     *
     * A version without an end shows on its start day only. Version dates are UTC "Y-m-d H:i:s".
     *
     * @param DateTimeImmutable $month any day of the month, in the admin time zone
     * @param int $firstDay first day of the week, 0 = Sunday
     * @param DataObject[] $updates with start_time and end_time
     * @return array<int, array<int, array{date: string, day: int, in_month: bool, updates: DataObject[]}>>
     */
    public static function weeks(DateTimeImmutable $month, int $firstDay, array $updates): array
    {
        [$day, $end] = self::range($month, $firstDay);
        $utc = new DateTimeZone('UTC');
        $weeks = [];
        $i = 0;
        while ($day < $end) {
            $next = $day->modify('+1 day');
            $from = $day->setTimezone($utc)->format('Y-m-d H:i:s');
            $to = $next->setTimezone($utc)->format('Y-m-d H:i:s');
            $weeks[intdiv($i++, 7)][] = [
                'date' => $day->format('Y-m-d'),
                'day' => (int) $day->format('j'),
                'in_month' => $day->format('m') === $month->format('m'),
                'updates' => array_values(array_filter(
                    $updates,
                    static fn (DataObject $update): bool => Scheduler::overlaps(
                        $from,
                        $to,
                        (string) $update->getData('start_time'),
                        $update->getData('end_time') ? (string) $update->getData('end_time') : null
                    )
                )),
            ];
            $day = $next;
        }
        return $weeks;
    }
}
