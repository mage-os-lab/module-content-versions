<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model\Source;

use MageOS\ContentVersions\Model\Update;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Update statuses.
 */
class Status implements OptionSourceInterface
{
    /**
     * @inheritdoc
     *
     * @return array<int, array{value: int, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Update::STATUS_SCHEDULED, 'label' => __('Scheduled')],
            ['value' => Update::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => Update::STATUS_FINISHED, 'label' => __('Finished')],
        ];
    }
}
