<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Core\Content\Cms;

use MarkupCarve\Shopware\Service\CarveRenderer;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * Resolves the `carve` CMS element. The element is self-contained: it holds a
 * `content` config field with Carve source. No data fetching is needed; the
 * storefront template renders the source through the |carve filter (server-side,
 * cached, SEO-safe).
 */
class CarveCmsElementResolver extends AbstractCmsElementResolver
{
    public function __construct(private readonly CarveRenderer $renderer)
    {
    }

    public function getType(): string
    {
        return 'carve';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        // Source stays in slot config (config.content.value); template renders it.
        // Pre-render to data so templates can also read a ready 'html' field.
        $config = $slot->getFieldConfig();
        $content = $config->get('content');
        $source = $content?->getValue();
        // setData() requires a Struct (not a plain array) on Shopware 6.7; ArrayStruct
        // keeps the template accessor `element.data.html` working via array access.
        $slot->setData(new ArrayStruct(['html' => $this->renderer->toHtml(is_string($source) ? $source : null)]));
    }
}
