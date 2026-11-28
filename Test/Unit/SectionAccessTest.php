<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Unit;

use MageOS\ContentVersions\Block\Adminhtml\EntityRevisions;
use MageOS\ContentVersions\Block\Adminhtml\EntityUpdates;
use MageOS\ContentVersions\Plugin\HideSections;
use Magento\Backend\Block\Template\Context;
use Magento\Cms\Model\Page\DataProvider;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests that an admin role without the module's ACL resource does not see its sections on the CMS forms.
 */
class SectionAccessTest extends TestCase
{
    /**
     * Without the resource, the Planned Versions and Revisions sections are not shown; with it, they are.
     */
    public function testSectionsAreHiddenWithoutTheResource(): void
    {
        $provider = $this->createStub(DataProvider::class);
        $meta = ['general' => ['arguments' => []]];

        $this->assertSame($meta, (new HideSections($this->authorization(true)))->afterGetMeta($provider, $meta));
        $hidden = (new HideSections($this->authorization(false)))->afterGetMeta($provider, $meta);
        foreach (['scheduled_changes', 'mageos_versions_revisions'] as $section) {
            $this->assertTrue($hidden[$section]['arguments']['data']['config']['componentDisabled']);
        }
    }

    /**
     * Without the resource, the sections render nothing, so no Restore button.
     */
    #[AllowMockObjectsWithoutExpectations] // the mocks Magento's ObjectManager helper creates
    public function testSectionContentIsEmptyWithoutTheResource(): void
    {
        $objectManager = new ObjectManager($this);
        $objectManager->prepareObjectManager([
            [JsonHelper::class, $this->createStub(JsonHelper::class)],
            [DirectoryHelper::class, $this->createStub(DirectoryHelper::class)],
        ]);
        foreach ([EntityRevisions::class, EntityUpdates::class] as $class) {
            $block = $objectManager->getObject($class, [
                'context' => $objectManager->getObject(Context::class, [
                    'authorization' => $this->authorization(false),
                ]),
            ]);
            $this->assertSame('', $block?->toHtml(), $class);
        }
    }

    /**
     * An authorization that grants MageOS_ContentVersions::update or not.
     *
     * @param bool $allowed
     */
    private function authorization(bool $allowed): AuthorizationInterface
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects($this->atLeastOnce())->method('isAllowed')
            ->with('MageOS_ContentVersions::update')->willReturn($allowed);
        return $authorization;
    }
}
