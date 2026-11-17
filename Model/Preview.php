<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Storefront preview of an update: any storefront URL with ?version_preview=<id>&version_token=<signature>
 * is rendered with that update's content, without touching the live page/block.
 */
class Preview
{
    /**
     * @var Update|null
     */
    private ?Update $current = null;

    /**
     * @var bool
     */
    private bool $resolved = false;

    /**
     * @param RequestInterface $request
     * @param UpdateFactory $updateFactory
     * @param DeploymentConfig $deploymentConfig
     * @param StoreManagerInterface $storeManager
     * @param Entities $entities
     * @param ResourceModel\Update $resource
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UpdateFactory $updateFactory,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly Entities $entities,
        private readonly ResourceModel\Update $resource
    ) {
    }

    /**
     * The link is the only credential: the token is a signature of the update ID with the installation key.
     *
     * @param int $updateId
     */
    public function token(int $updateId): string
    {
        return hash_hmac('sha256', 'version-preview-' . $updateId, (string) $this->deploymentConfig->get('crypt/key'));
    }

    /**
     * Signed storefront preview URL.
     *
     * Blocks have no URL of their own: their preview starts on the home page, the parameters work on any URL.
     *
     * @param Update $update
     */
    public function url(Update $update): string
    {
        $path = '';
        if ($update->getEntityType() === 'page') {
            try {
                $path = $this->entities->load('page', (int) $update->getEntityId())->getIdentifier();
            } catch (NoSuchEntityException $e) {
                $path = '';
            }
        }
        return $this->storeManager->getDefaultStoreView()?->getBaseUrl() . $path . '?' . http_build_query([
            'version_preview' => $update->getId(),
            'version_token' => $this->token((int) $update->getId()),
        ]);
    }

    /**
     * The update being previewed by the current request, when its token is valid and it is not finished.
     *
     * A leaked link stops working once the version is over (or deleted).
     */
    public function current(): ?Update
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $id = (int) $this->request->getParam('version_preview');
            $token = (string) $this->request->getParam('version_token');
            if ($id > 0 && hash_equals($this->token($id), $token)) {
                $update = $this->updateFactory->create();
                $this->resource->load($update, $id);
                $live = $update->getId() && (int) $update->getStatus() !== Update::STATUS_FINISHED;
                $this->current = $live ? $update : null;
            }
        }
        return $this->current;
    }

    /**
     * Staged values to overlay on this entity, empty outside of its preview.
     *
     * @param string $type
     * @param int $entityId
     * @return array<string, string>
     */
    public function dataFor(string $type, int $entityId): array
    {
        $update = $this->current();
        return $update && $update->getEntityType() === $type && (int) $update->getEntityId() === $entityId
            ? $update->getStagedData()
            : [];
    }
}
