<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Command;

use MarkupCarve\Shopware\Command\CarveRenderCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
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

    /**
     * The ANSI target uses --term, not --ansi: Symfony reserves --ansi/--no-ansi
     * globally, so registering an --ansi option collides once the command is added
     * to an Application. Running through a real Application here guards that
     * regression (a bare CommandTester would not surface the collision) and proves
     * --term emits ANSI escape sequences.
     */
    public function testRendersAnsiViaTermThroughApplication(): void
    {
        $application = new Application();
        $application->add(new CarveRenderCommand());

        $tester = new CommandTester($application->find('carve:render'));
        $tester->execute(
            ['source' => '-', '--text-input' => '*x*', '--term' => true],
            ['decorated' => true],
        );

        self::assertStringContainsString("\033[", $tester->getDisplay());
    }
}
