<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * A scheduled version of a CMS page or block: the staged field values replace the live ones
 * from start_time to end_time (or for good when there is no end).
 */
class Update extends AbstractModel
{
    public const STATUS_SCHEDULED = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_FINISHED = 2;

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Update::class);
    }

    /**
     * Field values the update puts live.
     *
     * @return array<string, string>
     */
    public function getStagedData(): array
    {
        return json_decode((string) $this->getData('data'), true) ?: [];
    }

    /**
     * Field values to put back when the update ends.
     *
     * @return array<string, ?string>
     */
    public function getOriginalData(): array
    {
        return json_decode((string) $this->getData('original'), true) ?: [];
    }
}
