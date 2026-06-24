<?php declare(strict_types=1);

namespace Carve\Shopware\Service;

use Carve\CarveConverter;
use Carve\Node\Inline\InlineExtension;
use Carve\Node\Inline\Link;
use Carve\Node\Inline\Text;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Context-aware Carve rendering. Builds a per-call converter (safe mode on) with
 * the :product[SKU] inline extension bound to the active sales channel, so authored
 * product references resolve to storefront links for the current channel.
 *
 * `:product[SKU]` is parsed by carve-php as a generic InlineExtension node (type
 * "product"). A render.inline_extension event listener intercepts those nodes and
 * resolves them to <a> links via a product repository lookup.
 *
 * Per-call because the render listener closure captures the SalesChannelContext;
 * do not share this converter across requests.
 */
class CarveContextRenderer
{
    /**
     * @param EntityRepository<\Shopware\Core\Content\Product\ProductCollection> $productRepository
     */
    public function __construct(private readonly EntityRepository $productRepository)
    {
    }

    public function toHtml(?string $source, SalesChannelContext $context): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        $converter = new CarveConverter(safeMode: true);

        $converter->on('render.inline_extension', function (\Carve\Event\RenderEvent $event) use ($context): void {
            /** @var InlineExtension $node */
            $node = $event->getNode();
            if (!$node instanceof InlineExtension || $node->getExtensionType() !== 'product') {
                return;
            }

            $sku = trim($event->getChildrenHtml());

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productNumber', $sku));
            $criteria->setLimit(1);

            /** @var \Shopware\Core\Content\Product\ProductEntity|null $product */
            $product = $this->productRepository->search($criteria, $context->getContext())->first();

            if ($product === null) {
                $event->setHtml(htmlspecialchars($sku, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

                return;
            }

            $name = (string) ($product->getTranslation('name') ?? $product->getName() ?? $sku);
            $url = '/detail/' . $product->getId();
            $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $event->setHtml('<a href="' . $escapedUrl . '">' . $escapedName . '</a>');
        });

        return $converter->convert($source);
    }
}
