<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarveIncludeCache;
use MarkupCarve\Shopware\Service\CarveIncludeGate;
use MarkupCarve\Shopware\Service\CarveIncludeResult;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CarveIncludeCacheTest extends CarveIncludeTestCase
{
    public function testMissingFilesAreTaggedForDeploymentInvalidation(): void
    {
        $root = $this->makeRoot();
        $events = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (object $event) use (&$events): object {
            $events[] = $event;

            return $event;
        });
        $cache = new CarveIncludeCache($dispatcher, new CarveIncludeGate($this->makeConfig($root)));
        $cache->tag(new CarveIncludeResult('', true, dependencies: [
            ['path' => 'missing.crv', 'resolved' => false],
            ['path' => CarveIncludeResult::OUTSIDE_ROOT, 'resolved' => false],
        ]));
        self::assertCount(2, $events);
        self::assertInstanceOf(AddCacheTagEvent::class, $events[0]);
        self::assertSame([CarveIncludeCache::ALL], $events[0]->tags);
        self::assertInstanceOf(AddCacheTagEvent::class, $events[1]);
        self::assertSame([$cache->pathTag('missing.crv')], $events[1]->tags);
        self::assertStringNotContainsString($root, $events[1]->tags[0]);
    }

    public function testLiteralIncludesDoNotTagFileDependencies(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');
        $cache = new CarveIncludeCache($dispatcher, new CarveIncludeGate($this->makeConfig(null)));
        $cache->tag(new CarveIncludeResult('{{ missing.crv }}'));
    }
}
