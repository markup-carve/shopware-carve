<?php declare(strict_types=1);

namespace Carve\Shopware\Service;

use Carve\CarveConverter;

/**
 * Thin wrapper around markup-carve/carve-php.
 *
 * HTML rendering runs with safe mode ON: authored raw HTML is escaped, dangerous
 * URL schemes (javascript:, data:, vbscript:, file:) are neutralized, and
 * on-event/srcdoc/formaction attribute values are stripped. Output is therefore safe to emit
 * into the storefront without further sanitizing - the reason the Twig `carve`
 * filter may mark it is_safe => html.
 *
 * One converter per target is reused across calls; convert() is a full parse+render
 * with no per-document state leak.
 */
class CarveRenderer
{
    private CarveConverter $html;
    private CarveConverter $text;
    private CarveConverter $markdown;

    public function __construct()
    {
        $this->html = new CarveConverter(safeMode: true);
        $this->text = CarveConverter::plainText();
        $this->markdown = CarveConverter::markdown();
    }

    public function toHtml(?string $source): string
    {
        return $this->render($this->html, $source);
    }

    public function toText(?string $source): string
    {
        return $this->render($this->text, $source);
    }

    public function toMarkdown(?string $source): string
    {
        return $this->render($this->markdown, $source);
    }

    private function render(CarveConverter $converter, ?string $source): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        return $converter->convert($source);
    }
}
