<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CarveIncludeCache
{
    /**
     * @var string
     */
    public const ALL = 'carve-includes';

    public function __construct(private readonly EventDispatcherInterface $collector, private readonly CarveIncludeGate $gate)
    {
    }

    public function tag(CarveIncludeResult $result): void
    {
        if (!$result->expanded) {
            return;
        }
        $this->collector->dispatch(new AddCacheTagEvent(self::ALL));
        foreach ($result->dependencies as $dependency) {
            if ($dependency['path'] !== CarveIncludeResult::OUTSIDE_ROOT) {
                $this->collector->dispatch(new AddCacheTagEvent($this->pathTag($dependency['path'])));
            }
        }
    }

    public function pathTag(string $path): string
    {
        return self::ALL . '-' . hash('sha256', $this->gate->root() . '/' . $path);
    }
}
