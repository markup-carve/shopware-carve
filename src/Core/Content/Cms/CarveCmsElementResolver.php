<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Core\Content\Cms;

use MarkupCarve\Shopware\Service\CarveContextRenderer;
use MarkupCarve\Shopware\Service\CarveIncludeCache;
use MarkupCarve\Shopware\Service\CarveIncludeResult;
use MarkupCarve\Shopware\Service\CarveRenderer;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * Resolves static or mapped Carve source to HTML with sales-channel references.
 * Static CMS source can expand approved file includes.
 */
class CarveCmsElementResolver extends AbstractCmsElementResolver
{
    public function __construct(
        private readonly CarveRenderer $renderer,
        private readonly ?CarveContextRenderer $contextRenderer = null,
        private readonly ?CarveIncludeCache $cache = null,
    ) {
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
        $content = $slot->getFieldConfig()->get('content');
        $source = $content?->getValue();
        $mapped = $content?->isMapped() ?? false;
        $includes = $content?->isStatic() ?? false;
        if ($mapped) {
            $source = is_string($source) && $resolverContext instanceof EntityResolverContext
                ? $this->resolveEntityValue($resolverContext->getEntity(), $source) : null;
        }
        $source = is_string($source) ? $source : null;
        $namespace = 'cms-' . $slot->getUniqueIdentifier();
        if ($this->contextRenderer !== null) {
            $result = $this->contextRenderer->render($source, $resolverContext->getSalesChannelContext(), $includes, $namespace);
        } else {
            $result = !$includes
                ? new CarveIncludeResult($this->renderer->toHtml($source))
                : $this->renderer->toHtmlWithIncludes($source, $resolverContext->getSalesChannelContext()->getContext());
        }
        $this->cache?->tag($result);
        $slot->setData(new ArrayStruct([
            'html' => $result->html,
            'carveIncludeDependencies' => $result->dependencies,
            'carveIncludeWarnings' => $result->warnings,
            'carveSource' => $source,
        ]));
    }
}
