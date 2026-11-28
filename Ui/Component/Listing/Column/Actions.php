<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Ui\Component\Listing\Column;

use MageOS\ContentVersions\Model\Update;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit / Preview / Duplicate / Restore / Delete links of the updates grid.
 */
class Actions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritdoc
     *
     * @param array<string,mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource)
    {
        foreach ($dataSource['data']['items'] ?? [] as $i => $item) {
            $id = ['update_id' => $item['update_id']];
            $dataSource['data']['items'][$i][$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl('mageos_versions/update/edit', $id),
                    'label' => __('Edit'),
                ],
                'preview' => [
                    'href' => $this->urlBuilder->getUrl('mageos_versions/update/preview', $id),
                    'label' => __('Preview'),
                    'target' => '_blank',
                ],
                'duplicate' => [
                    'href' => $this->urlBuilder->getUrl('mageos_versions/update/edit', ['from' => $item['update_id']]),
                    'label' => __('Duplicate'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl('mageos_versions/update/delete', $id),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete "%1"', $this->escaper->escapeHtml($item['name'])),
                        'message' => __('Delete this update? If it is active, the previous content is restored.'),
                    ],
                    'post' => true,
                ],
            ];
            if ((int) ($item['status'] ?? 0) === Update::STATUS_FINISHED) {
                $dataSource['data']['items'][$i][$this->getData('name')]['restore'] = [
                    'href' => $this->urlBuilder->getUrl('mageos_versions/update/restore', $id),
                    'label' => __('Restore'),
                    'confirm' => [
                        'title' => __('Restore "%1"', $this->escaper->escapeHtml($item['name'])),
                        'message' => __('Put the content of this version live again now, with no end date?'),
                    ],
                    'post' => true,
                ];
            }
        }
        return $dataSource;
    }
}
