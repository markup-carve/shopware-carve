<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Node\Inline\InlineExtension;

class CarvePresentation
{
    public function register(CarveConverter $converter): void
    {
        $converter->on('render.inline_extension', static function (RenderEvent $event): void {
            $node = $event->getNode();
            if (!$node instanceof InlineExtension || $node->getExtensionType() !== 'badge') {
                return;
            }

            $value = trim(html_entity_decode(strip_tags((string)$event->getChildrenHtml()), ENT_QUOTES, 'UTF-8'));
            [$label, $inlineVariant] = array_pad(explode('|', $value, 2), 2, '');
            $variant = $node->getAttribute('variant') ?: $inlineVariant;
            if (!in_array($variant, ['info', 'success', 'warning', 'danger'], true)) {
                $variant = 'info';
            }

            $event->setHtml('<span class="carve-badge carve-badge--' . $variant . '">'
                . htmlspecialchars(trim($label), ENT_QUOTES, 'UTF-8') . '</span>');
        });
    }
}
