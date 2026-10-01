<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\Node\Document;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class CarveContextRenderer
{
    public function __construct(
        private readonly CarveRenderer $renderer,
        private readonly CarveConverterFactory $factory,
        private readonly CarveCommerce $commerce,
        private readonly LanguageLocaleCodeProvider $locales,
        private readonly CarveResources $resources,
        private readonly SeoUrlPlaceholderHandlerInterface $seo,
        private readonly RequestStack $requests,
    ) {
    }

    public function toHtml(?string $source, SalesChannelContext $context, ?string $namespace = null): string
    {
        return $this->render($source, $context, false, $namespace)->html;
    }

    public function render(?string $source, SalesChannelContext $context, bool $includes = false, ?string $namespace = null): CarveIncludeResult
    {
        if ($source === null || trim($source) === '') {
            return new CarveIncludeResult('');
        }
        $locale = $this->locales->getLocaleForLanguageId($context->getLanguageId());
        // Each converter's hooks hold exactly one context and die after this render.
        $converter = $this->factory->create($context->getSalesChannelId(), $locale);
        $this->commerce->register($converter, $context);
        $this->resources->register($converter, $context);
        $prepare = fn (Document $document) => $this->commerce->prepare($document, $context);
        if ($includes) {
            $result = $this->renderer->toHtmlWithIncludes(
                $source,
                $context->getContext(),
                $context->getSalesChannelId(),
                $locale,
                $namespace,
                $converter,
                $prepare,
            );

            return $this->resolveUrls($result, $context);
        }
        $document = $converter->parse($source);
        $prepare($document);

        return $this->resolveUrls(new CarveIncludeResult(FragmentNamespace::apply($converter->render($document), $namespace)), $context);
    }

    private function resolveUrls(CarveIncludeResult $result, SalesChannelContext $context): CarveIncludeResult
    {
        $request = $this->requests->getCurrentRequest();
        if (in_array('storefront', $request?->attributes->get('_routeScope', []) ?? [], true)) {
            return $result;
        }
        $host = CarveStorefrontHost::resolve($request, $context);

        return new CarveIncludeResult(
            $this->seo->replace($result->html, $host, $context),
            $result->expanded,
            $result->warnings,
            $result->dependencies,
            $result->suppressedWarnings,
        );
    }
}
