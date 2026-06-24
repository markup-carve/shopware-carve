<?php declare(strict_types=1);

namespace Carve\Shopware\Tests\Command;

use Carve\Shopware\Command\CarveRenderCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CarveRenderCommandTest extends TestCase
{
    public function testRendersHtmlFromStdin(): void
    {
        $cmd = new CarveRenderCommand();
        $tester = new CommandTester($cmd);
        $tester->setInputs([]);
        $tester->execute(['source' => '-', '--text-input' => '*x*', '--html' => true]);
        self::assertStringContainsString('<strong>x</strong>', $tester->getDisplay());
    }

    public function testRendersPlain(): void
    {
        $cmd = new CarveRenderCommand();
        $tester = new CommandTester($cmd);
        $tester->execute(['source' => '-', '--text-input' => '*x*', '--plain' => true]);
        self::assertStringNotContainsString('<strong>', $tester->getDisplay());
    }
}
