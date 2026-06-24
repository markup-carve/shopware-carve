<?php declare(strict_types=1);

namespace Carve\Shopware\Tests\Twig;

use Carve\Shopware\Service\CarveRenderer;
use Carve\Shopware\Twig\CarveExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;

class CarveExtensionTest extends TestCase
{
    private CarveExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new CarveExtension(new CarveRenderer());
    }

    public function testRegistersThreeFilters(): void
    {
        $names = array_map(static fn (TwigFilter $f): string => $f->getName(), $this->ext->getFilters());
        self::assertContains('carve', $names);
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
        self::assertContains('html', $carve->getSafe(new \Twig\Node\Node()) ?? []);
    }

    public function testRenderHtml(): void
    {
        self::assertStringContainsString('<strong>x</strong>', $this->ext->renderHtml('*x*'));
    }
}
