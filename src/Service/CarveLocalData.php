<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Node\Block\CodeBlock;
use MarkupCarve\Carve\Node\Inline\InlineExtension;

class CarveLocalData
{
    public function register(CarveConverter $converter): void
    {
        $converter->on('render.inline_extension', static function (RenderEvent $event): void {
            $node = $event->getNode();
            if (!$node instanceof InlineExtension || $node->getExtensionType() !== 'spark') {
                return;
            }
            $source = trim(html_entity_decode(strip_tags((string)$event->getChildrenHtml()), ENT_QUOTES, 'UTF-8'));
            $event->setHtml(self::spark($source) ?? self::escape($source));
        });
        $converter->on('render.code_block', static function (RenderEvent $event): void {
            $node = $event->getNode();
            if (!$node instanceof CodeBlock || $node->getLanguage() !== 'csv') {
                return;
            }
            $table = self::csvTable($node->getContent());
            if ($table !== null) {
                $event->setHtml($table);
            }
        });
    }

    private static function spark(string $source): ?string
    {
        if (strlen($source) > 256 || preg_match('/^-?\d+(?:\.\d+)?(?:,-?\d+(?:\.\d+)?){1,31}$/D', $source) !== 1) {
            return null;
        }
        $values = array_map('floatval', explode(',', $source));
        foreach ($values as $value) {
            if (!is_finite($value)) {
                return null;
            }
        }
        $minimum = min($values);
        $range = max($values) - $minimum;
        if (!is_finite($range)) {
            return null;
        }
        $points = [];
        foreach ($values as $index => $value) {
            $x = round($index * 100 / (count($values) - 1), 1);
            $y = $range === 0.0 ? 12 : round(24 - ($value - $minimum) / $range * 24, 1);
            $points[] = $x . ',' . $y;
        }

        return '<svg class="carve-spark" viewBox="0 0 100 24" role="img" aria-label="Trend: '
            . self::escape($source) . '"><polyline points="' . implode(' ', $points)
            . '" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>';
    }

    private static function csvTable(string $source): ?string
    {
        if (strlen($source) > 32768) {
            return null;
        }
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return null;
        }
        fwrite($stream, $source);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            if (count($rows) >= 128 || count($row) > 32 || ($rows !== [] && count($row) !== count($rows[0]))) {
                fclose($stream);

                return null;
            }
            $rows[] = $row;
        }
        fclose($stream);
        if (count($rows) < 2 || count($rows[0]) < 2) {
            return null;
        }
        $html = '<div class="carve-csv-table"><table><thead><tr>';
        foreach ($rows[0] as $cell) {
            $html .= '<th scope="col">' . self::escape($cell ?? '') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach (array_slice($rows, 1) as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . self::escape($cell ?? '') . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
