<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Command;

use MarkupCarve\Shopware\Command\CarveLintCommand;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Service\CarveStoredSource;
use MarkupCarve\Shopware\Service\CarveStoredSources;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CarveLintCommandTest extends TestCase
{
    public function testStoredFieldsAreLintedPerEntity(): void
    {
        $sources = $this->createMock(CarveStoredSources::class);
        $sources->expects(self::once())->method('sources')->with(['product'])->willReturn([
            new CarveStoredSource('product', str_repeat('a', 32), str_repeat('b', 32), 'carve_body', "- item\n\n  text\n - x\n"),
            new CarveStoredSource('product', str_repeat('c', 32), str_repeat('b', 32), 'carve_body', "Clean.\n"),
        ]);
        $tester = new CommandTester(new CarveLintCommand($this->renderer(), $sources));
        $tester->execute(['--entity' => ['product']]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('product:' . str_repeat('a', 32) . ':' . str_repeat('b', 32) . ':carve_body:4:2', $tester->getDisplay());
        self::assertStringContainsString('list-marker-below-content-column', $tester->getDisplay());
        self::assertStringContainsString('in 2 source(s)', $tester->getDisplay());
    }

    public function testCleanSourcesSucceed(): void
    {
        $sources = $this->createStub(CarveStoredSources::class);
        $sources->method('sources')->willReturn([new CarveStoredSource('cms', str_repeat('a', 32), str_repeat('b', 32), 'content', "Clean.\n")]);
        $tester = new CommandTester(new CarveLintCommand($this->renderer(), $sources));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('No Carve problems found in 1 source(s).', $tester->getDisplay());
    }

    public function testFilesAreLintedInsteadOfStoredFields(): void
    {
        $file = (string)tempnam(sys_get_temp_dir(), 'carve-lint-');
        file_put_contents($file, "**bold**\n");
        $sources = $this->createMock(CarveStoredSources::class);
        $sources->expects(self::never())->method('sources');
        $tester = new CommandTester(new CarveLintCommand($this->renderer(), $sources));
        $status = $tester->execute(['paths' => [$file, $file . '-missing']]);
        unlink($file);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString($file . ':1:1', $tester->getDisplay());
        self::assertStringContainsString('is not readable', $tester->getDisplay());
    }

    public function testIncludeRootLintsIncludedFiles(): void
    {
        $root = sys_get_temp_dir() . '/carve-lint-root-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/main.crv', "{{ part.crv }}\n");
        file_put_contents($root . '/part.crv', "**bold**\n");
        $tester = new CommandTester(new CarveLintCommand($this->renderer(), $this->createStub(CarveStoredSources::class)));
        $status = $tester->execute(['paths' => [$root . '/main.crv'], '--include-root' => $root]);
        unlink($root . '/main.crv');
        unlink($root . '/part.crv');
        rmdir($root);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('markdown-strong-double-star', $tester->getDisplay());
        self::assertStringContainsString('part.crv:1:1', $tester->getDisplay());
        self::assertSame(Command::INVALID, (new CommandTester(new CarveLintCommand($this->renderer(), $this->createStub(CarveStoredSources::class))))->execute(['--include-root' => sys_get_temp_dir()]));
    }

    public function testAnUnknownEntityIsRefused(): void
    {
        $tester = new CommandTester(new CarveLintCommand($this->renderer(), $this->createStub(CarveStoredSources::class)));

        self::assertSame(Command::INVALID, $tester->execute(['--entity' => ['order']]));
    }

    private function renderer(): CarveRenderer
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturn(null);

        return new CarveRenderer($config);
    }
}
