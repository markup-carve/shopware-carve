<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Twig;

use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Twig\CarveExtension;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Twig\Node\Node;
use Twig\TwigFilter;

class CarveExtensionTest extends TestCase
{
    private CarveExtension $ext;

    protected function setUp(): void
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturn(null);

        $this->ext = new CarveExtension(new CarveRenderer($config));
    }

    public function testRegistersThreeFilters(): void
    {
        $names = array_map(static fn (TwigFilter $f): string => $f->getName(), $this->ext->getFilters());
        self::assertContains('carve', $names);
        self::assertContains('carve_ugc', $names);
        self::assertContains('carve_text', $names);
        self::assertContains('carve_md', $names);
    }

    public function testCarveFilterIsHtmlSafe(): void
    {
        $carve = null;
        foreach ($this->ext->getFilters() as $f) {
            if ($f->getName() === 'carve') {
                $carve = $f;
            }
        }
        self::assertNotNull($carve);
        self::assertContains('html', $carve->getSafe(new Node()) ?? []);
    }

    public function testCarveUgcFilterIsHtmlSafe(): void
    {
        $ugc = null;
        foreach ($this->ext->getFilters() as $f) {
            if ($f->getName() === 'carve_ugc') {
                $ugc = $f;
            }
        }
        self::assertNotNull($ugc);
        self::assertContains('html', $ugc->getSafe(new Node()) ?? []);
    }

    public function testRenderHtml(): void
    {
        self::assertStringContainsString('<strong>x</strong>', $this->ext->renderHtml('*x*'));
    }

    public function testRenderHtmlUgc(): void
    {
        // Comment profile allows bold but strips headings.
        self::assertStringContainsString('<strong>x</strong>', $this->ext->renderHtmlUgc('*x*'));
        self::assertStringNotContainsString('<h1', $this->ext->renderHtmlUgc('# Heading'));
    }
}
