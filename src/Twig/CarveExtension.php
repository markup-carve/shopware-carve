<?php declare(strict_types=1);

namespace MarkupCarve\Shopware\Twig;

use MarkupCarve\Shopware\Service\CarveRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig filters:
 *   {{ src|carve }}       -> safe HTML  (is_safe => html; sound only due to safe mode)
 *   {{ src|carve_ugc }}   -> safe HTML, always comment profile + safe mode (UGC/reviews)
 *   {{ src|carve_text }}  -> plain text (e.g. mail text part)
 *   {{ src|carve_md }}    -> Markdown
 */
class CarveExtension extends AbstractExtension
{
    public function __construct(private readonly CarveRenderer $renderer)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('carve', [$this, 'renderHtml'], ['is_safe' => ['html']]),
            new TwigFilter('carve_ugc', [$this, 'renderHtmlUgc'], ['is_safe' => ['html']]),
            new TwigFilter('carve_text', [$this, 'renderText']),
            new TwigFilter('carve_md', [$this, 'renderMarkdown']),
        ];
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
