<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model\Update;

use MageOS\ContentVersions\Model\Entities;
use MageOS\ContentVersions\Model\ResourceModel\Update\CollectionFactory;
use MageOS\ContentVersions\Model\Update;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data of the update edit form: the update itself, the fields it changes, and the current content of its
 * page/block for the others. A new update starts from the page/block given by the "type" and "id" parameters, or
 * from a copy of the name and fields of the update given by the "from" parameter.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private ?array $loaded = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param Entities $entities
     * @param DataPersistorInterface $dataPersistor
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly Entities $entities,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * The update row over the live content, or the live content alone for a new one.
     *
     * Values rejected by a failed save win.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function getData()
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }
        $id = (int) $this->request->getParam($this->getRequestFieldName());
        $update = $id ? $this->collection->getItemById($id) : null;
        $source = $update ? null : $this->source();
        $row = match (true) {
            $update !== null => array_diff_key($update->getData(), ['data' => 1, 'original' => 1]),
            $source !== null => [
                'entity_type' => $source->getEntityType(),
                'entity_id' => (int) $source->getEntityId(),
                'name' => $source->getName(),
            ],
            default => ['entity_type' => $this->entityType(), 'entity_id' => (int) $this->request->getParam('id')],
        };
        try {
            $entity = $this->entities->load((string) $row['entity_type'], (int) $row['entity_id']);
            foreach (Entities::FIELDS[$row['entity_type']] as $field) {
                $row[$field] = $entity->getData($field);
            }
        } catch (NoSuchEntityException $e) {
            $row = $update ? $row : [];
        }
        $copied = $update ?? $source;
        if ($copied) {
            $row = $copied->getStagedData() + $row;
        }
        $rejected = $this->dataPersistor->get('mageos_versions_update');
        if ($rejected) {
            $row = $rejected + $row;
            $this->dataPersistor->clear('mageos_versions_update');
        }
        return $this->loaded = [$update ? $update->getId() : '' => $row];
    }

    /**
     * One form for both types: hides the fields a block does not have.
     *
     * @return array<string, mixed>
     */
    public function getMeta()
    {
        $meta = parent::getMeta();
        foreach (array_diff(Entities::FIELDS['page'], Entities::FIELDS[$this->entityType()] ?? []) as $field) {
            $meta['content']['children'][$field]['arguments']['data']['config']['visible'] = false;
        }
        return $meta;
    }

    /**
     * Type of the edited update, or the "type" parameter for a new one.
     */
    private function entityType(): string
    {
        $id = (int) $this->request->getParam($this->getRequestFieldName());
        $update = $id ? $this->collection->getItemById($id) : null;
        $update ??= $this->source();
        return (string) ($update ? $update->getEntityType() : $this->request->getParam('type'));
    }

    /**
     * The update copied by a new one ("from" parameter), if any.
     */
    private function source(): ?Update
    {
        $from = (int) $this->request->getParam('from');
        $source = $from ? $this->collection->getItemById($from) : null;
        return $source instanceof Update ? $source : null;
    }
}
