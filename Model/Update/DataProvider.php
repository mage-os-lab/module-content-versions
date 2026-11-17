<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model\Update;

use MageOS\ContentVersions\Model\Entities;
use MageOS\ContentVersions\Model\ResourceModel\Update\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data of the update edit form: the update itself plus its staged fields, flattened.
 * A new update starts from the current content of the page/block given by the "type" and "id" parameters.
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
     * The update row, or a new one prefilled with the live content; values rejected by a failed save win.
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
        if ($update) {
            $row = $update->getStagedData() + $update->getData();
            unset($row['data'], $row['original']);
        } else {
            $row = ['entity_type' => $this->entityType(), 'entity_id' => (int) $this->request->getParam('id')];
            try {
                $entity = $this->entities->load($row['entity_type'], $row['entity_id']);
                foreach (Entities::FIELDS[$row['entity_type']] as $field) {
                    $row[$field] = $entity->getData($field);
                }
            } catch (NoSuchEntityException $e) {
                $row = [];
            }
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
        return (string) ($update ? $update->getEntityType() : $this->request->getParam('type'));
    }
}
