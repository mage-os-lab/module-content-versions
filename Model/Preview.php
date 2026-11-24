<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Model;

use Magento\Cms\Model\Page;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Storefront preview of an update: any storefront URL with
 * ?version_preview=<id>&version_expires=<time>&version_token=<signature> is rendered with that update's content,
 * without touching the live page/block.
 */
class Preview
{
    /**
     * How long a preview link works, in seconds: long enough to send it for approval.
     */
    public const LIFETIME = 7 * 86400;

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
     * The link is the only credential: its token signs the update ID and the expiry with the installation key.
     *
     * @param int $updateId
     * @param int $expires Unix time
     * @param string $key
     */
    public static function sign(int $updateId, int $expires, string $key): string
    {
        return hash_hmac('sha256', 'version-preview-' . $updateId . '-' . $expires, $key);
    }

    /**
     * Is this the token of that update and expiry, not yet expired?
     *
     * @param int $updateId
     * @param int $expires Unix time
     * @param string $token
     * @param string $key
     * @param int $now Unix time
     */
    public static function valid(int $updateId, int $expires, string $token, string $key, int $now): bool
    {
        return $updateId > 0 && $expires >= $now && hash_equals(self::sign($updateId, $expires, $key), $token);
    }

    /**
     * Signed storefront preview URL, valid for LIFETIME, in a store view of the page / block.
     *
     * Blocks have no URL of their own: their preview starts on the home page, the parameters work on any URL.
     *
     * @param Update $update
     */
    public function url(Update $update): string
    {
        $path = '';
        $store = null;
        $expires = time() + self::LIFETIME;
        $query = [
            'version_preview' => $update->getId(),
            'version_expires' => $expires,
            'version_token' => self::sign((int) $update->getId(), $expires, $this->key()),
        ];
        try {
            $entity = $this->entities->load((string) $update->getEntityType(), (int) $update->getEntityId());
            $path = $entity instanceof Page ? (string) $entity->getIdentifier() : '';
            // ponytail: first store view of the content; let the editor choose if the others must be previewed.
            $storeIds = array_filter(array_map('intval', (array) $entity->getStores()));
            if ($storeIds) {
                $store = $this->storeManager->getStore(reset($storeIds));
                $query[StoreManagerInterface::PARAM_NAME] = $store->getCode();
            }
        } catch (NoSuchEntityException $e) {
            $store = null;
        }
        $store ??= $this->storeManager->getDefaultStoreView();
        return $store?->getBaseUrl() . $path . '?' . http_build_query($query);
    }

    /**
     * The update being previewed by the current request, when its link is valid and it is not finished.
     *
     * A leaked link stops working when it expires, and once the version is over (or deleted).
     */
    public function current(): ?Update
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $id = (int) $this->request->getParam('version_preview');
            $expires = (int) $this->request->getParam('version_expires');
            $token = (string) $this->request->getParam('version_token');
            if (self::valid($id, $expires, $token, $this->key(), time())) {
                $update = $this->updateFactory->create();
                $this->resource->load($update, $id);
                $live = $update->getId() && (int) $update->getStatus() !== Update::STATUS_FINISHED;
                $this->current = $live ? $update : null;
            }
        }
        return $this->current;
    }

    /**
     * The installation key that signs the links.
     */
    private function key(): string
    {
        return (string) $this->deploymentConfig->get('crypt/key');
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
