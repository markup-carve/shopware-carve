<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CarveAppBlockEvent extends Event
{
    private ?string $html = null;

    public function __construct(
        private readonly string $name,
        private readonly string $fallbackHtml,
        private readonly SalesChannelContext $context,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFallbackHtml(): string
    {
        return $this->fallbackHtml;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }
}
