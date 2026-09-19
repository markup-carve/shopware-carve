<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarveIncludeGate;
use MarkupCarve\Shopware\Service\CarveIncludeResult;
use RuntimeException;
use Shopware\Core\Framework\Context;

class CarveIncludeGateTest extends CarveIncludeTestCase
{
    public function testNoConfiguredRootLeavesInclusionOff(): void
    {
        $gate = new CarveIncludeGate($this->makeConfig(null));

        self::assertNull($gate->root());
        self::assertNull($gate->resolver());
    }

    public function testABlankRootLeavesInclusionOff(): void
    {
        $gate = new CarveIncludeGate($this->makeConfig('   '));

        self::assertNull($gate->resolver());
    }

    public function testARelativeRootIsRefused(): void
    {
        // `src` exists relative to the working directory, so a root that gets
        // canonicalized first would be accepted here.
        self::assertDirectoryExists(getcwd() . '/src');

        $gate = new CarveIncludeGate($this->makeConfig('src'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(CarveIncludeGate::CONFIG_KEY);
        $gate->resolver();
    }

    public function testTheDedicatedPrivilegeGatesAnAdministrationIdentity(): void
    {
        $gate = new CarveIncludeGate($this->makeConfig($this->makeRoot()));

        self::assertTrue($gate->isGranted($this->privilegedAdmin()));
        self::assertFalse($gate->isGranted($this->cmsOnlyAdmin()));
        // Shopware allows every non-administration source, so the storefront
        // render of already-authored content expands as the preview showed.
        self::assertTrue($gate->isGranted(Context::createDefaultContext()));
    }

    public function testAnIdentityOutsideTheRootIsMasked(): void
    {
        $root = $this->makeRoot();
        $gate = new CarveIncludeGate($this->makeConfig($root));

        self::assertSame('chapters/one.crv', $gate->containedIdentity($root . '/chapters/one.crv'));
        self::assertSame('.', $gate->containedIdentity($root));
        self::assertSame(CarveIncludeResult::OUTSIDE_ROOT, $gate->containedIdentity('/etc/passwd'));
        self::assertSame(CarveIncludeResult::OUTSIDE_ROOT, $gate->containedIdentity('../secret.crv'));
        self::assertSame(CarveIncludeResult::OUTSIDE_ROOT, $gate->containedIdentity('a/../../secret.crv'));
        self::assertSame('linked.crv', $gate->containedIdentity('linked.crv'));
    }
}
