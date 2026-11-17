<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Block\Adminhtml;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Save button of the update edit form.
 */
class SaveButton implements ButtonProviderInterface
{
    /**
     * @inheritdoc
     *
     * @return array<string, mixed>
     */
    public function getButtonData()
    {
        return [
            'label' => __('Save'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
