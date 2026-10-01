<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use DOMDocument;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension\TabsExtension;
use MarkupCarve\Shopware\Service\FragmentNamespace;
use PHPUnit\Framework\TestCase;

class FragmentNamespaceTest extends TestCase
{
    public function testFragmentsKeepTheirTabsAndFootnotesIndependent(): void
    {
        $converter = new CarveConverter();
        $converter->addExtension(new TabsExtension());
        $source = ":::: tabs\n::: tab \"One\"\nFirst\n:::\n::: tab \"Two\"\nSecond\n:::\n::::\n\nText[^n]\n\n[^n]: Note";
        $original = $converter->convert($source);
        $first = FragmentNamespace::apply($original, 'cms-first');
        $second = FragmentNamespace::apply($original, 'cms-second');
        self::assertSame($first, FragmentNamespace::apply($original, 'cms-first'));
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<html><body>' . $first . $second . '</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $ids = [];
        $names = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute('id')) {
                $id = $element->getAttribute('id');
                self::assertArrayNotHasKey($id, $ids);
                $ids[$id] = true;
            }
            if ($element->tagName === 'input') {
                $names[] = $element->getAttribute('name');
            }
        }
        self::assertCount(2, array_unique($names));
        foreach ($document->getElementsByTagName('a') as $anchor) {
            self::assertArrayHasKey(substr($anchor->getAttribute('href'), 1), $ids);
        }
        foreach ($document->getElementsByTagName('label') as $label) {
            self::assertArrayHasKey($label->getAttribute('for'), $ids);
        }
    }

    public function testExternalFragmentsAndTextArePreserved(): void
    {
        $html = '<h2 id="title">Über uns</h2><a href="https://example.com/#title">Outside</a><a href="#title">Here</a>';
        $scoped = FragmentNamespace::apply($html, 'entity');
        self::assertStringContainsString('Über uns', $scoped);
        self::assertStringContainsString('href="https://example.com/#title"', $scoped);
        self::assertStringNotContainsString('href="#title"', $scoped);
    }
}
