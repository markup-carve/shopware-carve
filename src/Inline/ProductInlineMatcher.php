<?php declare(strict_types=1);

namespace Carve\Shopware\Inline;

use Carve\Node\Inline\Link;
use Carve\Node\Inline\Text;
use Carve\Parser\MatcherContext;

/**
 * Parses `:product[SKU]` inline syntax. On a known SKU it emits a Link to the
 * product detail page with the product name as text; on an unknown SKU it emits
 * the literal SKU as inert text (no broken link). Resolution is delegated to a
 * lookup closure so the parser stays free of Shopware coupling (and testable).
 */
class ProductInlineMatcher
{
    /** @var \Closure(string): (array{name: string, url: string}|null) */
    private \Closure $lookup;

    /**
     * @param callable(string): (array{name: string, url: string}|null) $lookup
     */
    public function __construct(callable $lookup)
    {
        $this->lookup = \Closure::fromCallable($lookup);
    }

    public function toClosure(): \Closure
    {
        return function (string $text, int $pos, MatcherContext $ctx): ?array {
            if (!preg_match('/\G:product\[([^\]]+)\]/', $text, $m, 0, $pos)) {
                return null;
            }
            $sku = trim($m[1]);
            $product = ($this->lookup)($sku);
            $end = $pos + strlen($m[0]);

            if ($product === null) {
                return ['node' => new Text($sku), 'end' => $end];
            }

            $link = new Link($product['url']);
            $link->appendChild(new Text($product['name']));

            return ['node' => $link, 'end' => $end];
        };
    }
}
