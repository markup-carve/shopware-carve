<?php declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Core\Content\Cms;

use MarkupCarve\Shopware\Core\Content\Cms\CarveCmsElementResolver;
use MarkupCarve\Shopware\Service\CarveRenderer;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CarveCmsElementResolverTest extends TestCase
{
    private function makeRenderer(): CarveRenderer
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturn(null);

        return new CarveRenderer($config);
    }

    public function testTypeIsCarve(): void
    {
        $resolver = new CarveCmsElementResolver($this->makeRenderer());
        self::assertSame('carve', $resolver->getType());
    }

    public function testCollectReturnsNull(): void
    {
        $resolver = new CarveCmsElementResolver($this->makeRenderer());
        $slot = new \Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity();
        $slot->setUniqueIdentifier('s1');
        $rc = $this->createMock(\Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext::class);
        self::assertNull($resolver->collect($slot, $rc));
    }
}
