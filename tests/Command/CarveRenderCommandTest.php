<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Command;

use FilesystemIterator;
use MarkupCarve\Shopware\Command\CarveRenderCommand;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CarveRenderCommandTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $trees = [];

    protected function tearDown(): void
    {
        foreach ($this->trees as $tree) {
            foreach (
                array_reverse(iterator_to_array(new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tree, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST,
                ))) as $entry
            ) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($tree);
        }
        $this->trees = [];

        parent::tearDown();
    }

    public function testAFileInputRootsInclusionAtItsOwnDirectory(): void
    {
        $root = $this->makeTree(['main.crv' => "{{ child.crv }}\n", 'child.crv' => "Included.\n"]);

        $tester = new CommandTester(new CarveRenderCommand());
        $tester->execute(['source' => $root . '/main.crv', '--html' => true]);

        self::assertStringContainsString('Included.', $tester->getDisplay());
    }

    public function testAFileInputCannotReachOutsideItsOwnDirectory(): void
    {
        $root = $this->makeTree(['secret.crv' => "SECRET\n", 'book/main.crv' => "{{ ../secret.crv }}\n"]);

        $tester = new CommandTester(new CarveRenderCommand());
        $tester->execute(['source' => $root . '/book/main.crv', '--html' => true]);

        self::assertStringNotContainsString('SECRET', $tester->getDisplay());
        self::assertStringContainsString('{{ ../secret.crv }}', $tester->getDisplay());
    }

    public function testTheIncludeRootFlagWidensTheRootAndIsExpandedAgainstTheWorkingDirectory(): void
    {
        $root = $this->makeTree(['secret.crv' => "WIDENED\n", 'book/main.crv' => "{{ ../secret.crv }}\n"]);
        $previous = (string)getcwd();
        chdir($root);

        try {
            $tester = new CommandTester(new CarveRenderCommand());
            // A path typed on a command line MAY be resolved against the working
            // directory; the rule the resolver enforces is about a CONFIGURED root.
            $tester->execute(['source' => $root . '/book/main.crv', '--include-root' => '.', '--html' => true]);

            self::assertStringContainsString('WIDENED', $tester->getDisplay());
        } finally {
            chdir($previous);
        }
    }

    public function testStdinHasNoInferableRootSoDirectivesStayLiteral(): void
    {
        $root = $this->makeTree(['child.crv' => "SECRET\n"]);
        $previous = (string)getcwd();
        chdir($root);

        try {
            $tester = new CommandTester(new CarveRenderCommand());
            $tester->execute(['source' => '-', '--text-input' => '{{ child.crv }}', '--html' => true]);

            self::assertStringNotContainsString('SECRET', $tester->getDisplay());
            self::assertStringContainsString('{{ child.crv }}', $tester->getDisplay());
        } finally {
            chdir($previous);
        }
    }

    /**
     * @param array<string, string> $tree
     */
    private function makeTree(array $tree): string
    {
        $root = sys_get_temp_dir() . '/shopware-carve-cli-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->trees[] = $root;
        foreach ($tree as $relative => $contents) {
            $target = $root . '/' . $relative;
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            file_put_contents($target, $contents);
        }

        return $root;
    }

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
