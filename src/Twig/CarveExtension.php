<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Twig;

use MarkupCarve\Shopware\Service\CarveLiteral;
use MarkupCarve\Shopware\Service\CarveRenderer;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig filters:
 *   {{ src|carve }} -> safe HTML (is_safe => html; sound only due to safe mode)
 *   {{ src|carve_ugc }} -> safe HTML, always comment profile + safe mode (UGC/reviews)
 *   {{ src|carve_text }} -> plain text (e.g. mail text part)
 *   {{ src|carve_md }} -> Markdown
 */
class CarveExtension extends AbstractExtension
{
    public function __construct(
        private readonly CarveRenderer $renderer,
        private readonly ?LanguageLocaleCodeProvider $locales = null,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('carve', [$this, 'renderHtmlInContext'], ['needs_context' => true, 'is_safe' => ['html']]),
            new TwigFilter('carve_ugc', [$this, 'renderUgcInContext'], ['needs_context' => true, 'is_safe' => ['html']]),
            new TwigFilter('carve_text', [$this, 'renderText']),
            new TwigFilter('carve_md', [$this, 'renderMarkdown']),
            new TwigFilter('carve_escape', [CarveLiteral::class, 'escape']),
        ];
    }

    /**
     * @param array<string, mixed> $variables
     * @param string|null $source
     * @param string|null $namespace
     */
    public function renderHtmlInContext(array $variables, ?string $source, ?string $namespace = null): string
    {
        $context = $variables['context'] ?? null;
        if ($context instanceof SalesChannelContext) {
            return $this->renderer->toHtml(
                $source,
                $context->getSalesChannelId(),
                $this->locales?->getLocaleForLanguageId($context->getLanguageId()),
                $namespace,
            );
        }

        return $this->renderer->toHtml($source, namespace: $namespace);
    }

    /**
     * @param array<string, mixed> $variables
     * @param string|null $source
     */
    public function renderUgcInContext(array $variables, ?string $source): string
    {
        $context = $variables['context'] ?? null;

        return $this->renderer->toHtmlUgc(
            $source,
            $context instanceof SalesChannelContext ? $context->getSalesChannelId() : null,
            $context instanceof SalesChannelContext ? $this->locales?->getLocaleForLanguageId($context->getLanguageId()) : null,
        );
    }

    public function renderHtml(?string $source): string
    {
        return $this->renderer->toHtml($source);
    }

    public function renderHtmlUgc(?string $source): string
    {
        return $this->renderer->toHtmlUgc($source);
    }

    public function renderText(?string $source): string
    {
        return $this->renderer->toText($source);
    }

    public function renderMarkdown(?string $source): string
    {
        return $this->renderer->toMarkdown($source);
    }
}
