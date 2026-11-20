<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Inline;

use Closure;
use MarkupCarve\Shopware\Service\CarveHookCall;
use MarkupCarve\Shopware\Service\CarveSetup;

/**
 * Resolves the Carve inline extension `:product[SKU]` to a storefront product link.
 *
 * The hook claims `:product[...]` through a carve-php 0.2 inline extension matcher
 * and renders it at render time. A known SKU becomes an <a> to the product page;
 * an unknown SKU degrades to its inert text (no broken link).
 *
 * The SKU lookup is injected as a closure so this class stays free of Shopware
 * coupling and is unit-testable in isolation.
 */
class ProductInlineMatcher
{
    /**
     * @var \Closure(string): (array{name: string, url: string}|null)
     */
    private Closure $lookup;

    /**
     * @param callable(string): (array{name: string, url: string}|null) $lookup
     */
    public function __construct(callable $lookup)
    {
        $this->lookup = Closure::fromCallable($lookup);
    }

    public function register(CarveSetup $setup): void
    {
        $setup->on('inline', 'product', function (CarveHookCall $call): ?string {
            $sku = $call->argument();
            if ($sku === '') {
                return null;
            }

            $product = ($this->lookup)($sku);
            if ($product === null) {
                return $this->escape($sku);
            }

            return '<a href="' . $this->escape($product['url']) . '">' . $this->escape($product['name']) . '</a>';
        });
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
