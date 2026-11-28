<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Block\Adminhtml;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use MageOS\ContentVersions\Model\Calendar as CalendarGrid;
use MageOS\ContentVersions\Model\ResourceModel\Update\CollectionFactory;
use MageOS\ContentVersions\Model\Update;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\DataObject;

/**
 * Month calendar of the planned content versions.
 */
class Calendar extends Template
{
    /**
     * @var string
     */
    protected $_template = 'MageOS_ContentVersions::calendar.phtml';

    /**
     * @param Context $context
     * @param CollectionFactory $updates
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $updates,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The first day of the shown month (month=YYYY-MM, the current month otherwise), in the admin time zone.
     */
    public function getMonth(): DateTimeImmutable
    {
        $timezone = new DateTimeZone($this->_localeDate->getConfigTimezone());
        $month = (string) $this->getRequest()->getParam('month');
        try {
            $date = preg_match('/^\d{4}-\d{2}$/', $month)
                ? new DateTimeImmutable($month . '-01', $timezone)
                : new DateTimeImmutable('now', $timezone);
        } catch (Exception) {
            $date = new DateTimeImmutable('now', $timezone);
        }
        return $date->modify('first day of this month')->setTime(0, 0);
    }

    /**
     * Month and year of the shown month, in the admin locale.
     */
    public function getMonthTitle(): string
    {
        $month = $this->getMonth();
        return (string) $this->_localeDate->formatDateTime(
            $month,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            null,
            $month->getTimezone()->getName(),
            'LLLL y'
        );
    }

    /**
     * Weeks of the shown month, with the versions live each day.
     *
     * @return array<int, array<int, array{date: string, day: int, in_month: bool, updates: DataObject[]}>>
     */
    public function getWeeks(): array
    {
        $month = $this->getMonth();
        $firstDay = (int) $this->_scopeConfig->getValue('general/locale/firstday');
        [$start, $end] = CalendarGrid::range($month, $firstDay);
        $utc = new DateTimeZone('UTC');
        $items = $this->updates->create()
            ->addFieldToFilter('start_time', ['lt' => $end->setTimezone($utc)->format('Y-m-d H:i:s')])
            ->addFieldToFilter('end_time', [
                ['null' => true],
                ['gt' => $start->setTimezone($utc)->format('Y-m-d H:i:s')],
            ])
            ->setOrder('start_time', 'ASC')
            ->getItems();
        return CalendarGrid::weeks($month, $firstDay, array_values($items));
    }

    /**
     * Names of the days of the week, in the grid order.
     *
     * @return string[]
     */
    public function getDayNames(): array
    {
        $firstDay = (int) $this->_scopeConfig->getValue('general/locale/firstday');
        $names = [];
        $sunday = new DateTimeImmutable('2023-01-01', new DateTimeZone('UTC'));
        for ($i = 0; $i < 7; $i++) {
            $day = $sunday->modify('+' . (($firstDay + $i) % 7) . ' days');
            $names[] = (string) $this->_localeDate->formatDateTime(
                $day,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                null,
                'UTC',
                'EEE'
            );
        }
        return $names;
    }

    /**
     * URL of the calendar for another month.
     *
     * @param string $modifier "+1 month" or "-1 month"
     */
    public function getMonthUrl(string $modifier): string
    {
        return $this->getUrl('*/*/calendar', ['month' => $this->getMonth()->modify($modifier)->format('Y-m')]);
    }

    /**
     * URL to edit a version.
     *
     * @param DataObject $update
     */
    public function getEditUrl(DataObject $update): string
    {
        return $this->getUrl('*/*/edit', ['update_id' => $update->getData('update_id')]);
    }

    /**
     * URL of the versions grid.
     */
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    /**
     * CSS modifier of a version's status.
     *
     * @param DataObject $update
     */
    public function getStatusClass(DataObject $update): string
    {
        return match ((int) $update->getData('status')) {
            Update::STATUS_ACTIVE => 'active',
            Update::STATUS_FINISHED => 'finished',
            default => 'scheduled',
        };
    }
}
