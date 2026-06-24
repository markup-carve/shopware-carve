<?php declare(strict_types=1);

namespace Carve\Shopware\Tests\Core\Content\Cms;

use Carve\Shopware\Core\Content\Cms\CarveCmsElementResolver;
use Carve\Shopware\Service\CarveRenderer;
use PHPUnit\Framework\TestCase;

class CarveCmsElementResolverTest extends TestCase
{
    public function testTypeIsCarve(): void
    {
        $resolver = new CarveCmsElementResolver(new CarveRenderer());
        self::assertSame('carve', $resolver->getType());
    }

    public function testCollectReturnsNull(): void
    {
        $resolver = new CarveCmsElementResolver(new CarveRenderer());
        $slot = new \Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity();
        $slot->setUniqueIdentifier('s1');
        $rc = $this->createMock(\Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext::class);
        self::assertNull($resolver->collect($slot, $rc));
    }
}
