<?php declare(strict_types=1);

namespace Carve\Shopware\Twig;

use Carve\Shopware\Service\CarveContextRenderer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * {{ src|carve_ctx(context) }} -> safe HTML with :product[SKU] resolved for the
 * active sales channel. `context` is the storefront `context` variable
 * (SalesChannelContext), available in storefront templates.
 */
class CarveContextExtension extends AbstractExtension
{
    public function __construct(private readonly CarveContextRenderer $renderer)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('carve_ctx', [$this, 'render'], ['is_safe' => ['html']]),
        ];
    }

    public function render(?string $source, SalesChannelContext $context): string
    {
        return $this->renderer->toHtml($source, $context);
    }
}
