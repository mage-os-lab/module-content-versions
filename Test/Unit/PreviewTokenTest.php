<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Unit;

use MageOS\ContentVersions\Model\Preview;
use PHPUnit\Framework\TestCase;

/**
 * Tests the signature of the storefront preview link.
 */
class PreviewTokenTest extends TestCase
{
    /**
     * A link is accepted until its expiry, for its own version only, and its expiry cannot be pushed back.
     */
    public function testLinkIsValidUntilItExpiresAndCannotBeAltered(): void
    {
        $key = 'installation-key';
        $token = Preview::sign(7, 1000, $key);

        $this->assertTrue(Preview::valid(7, 1000, $token, $key, 1000), 'until its expiry');
        $this->assertFalse(Preview::valid(7, 1000, $token, $key, 1001), 'expired');
        $this->assertFalse(Preview::valid(7, 9999, $token, $key, 1000), 'expiry pushed back');
        $this->assertFalse(Preview::valid(8, 1000, $token, $key, 1000), 'another version');
        $this->assertFalse(Preview::valid(7, 1000, $token, 'other-key', 1000), 'another installation');
    }

    /**
     * A link signed by 1.0.0 (version ID only, no expiry) is refused.
     */
    public function testLinkWithoutExpiryIsRefused(): void
    {
        $key = 'installation-key';
        $legacy = hash_hmac('sha256', 'version-preview-7', $key);

        $this->assertFalse(Preview::valid(7, 0, $legacy, $key, 1000));
    }
}
