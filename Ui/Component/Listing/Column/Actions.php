<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Ui\Component\Listing\Column;

use MageOS\ContentVersions\Model\Preview;
use MageOS\ContentVersions\Model\UpdateFactory;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit / Preview / Delete links of the updates grid.
 */
class Actions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Preview $preview
     * @param UpdateFactory $updateFactory
     * @param Escaper $escaper
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Preview $preview,
        private readonly UpdateFactory $updateFactory,
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
                    'href' => $this->preview->url($this->updateFactory->create()->setData($item)),
                    'label' => __('Preview'),
                    'target' => '_blank',
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
        }
        return $dataSource;
    }
}
