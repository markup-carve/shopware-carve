<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Command;

use MarkupCarve\Shopware\Command\CarveConvertCommand;
use MarkupCarve\Shopware\Service\CarveStoredSource;
use MarkupCarve\Shopware\Service\CarveStoredSources;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CarveConvertCommandTest extends TestCase
{
    public function testTheDefaultIsADryRun(): void
    {
        $sources = $this->sources();
        $sources->expects(self::never())->method('write');
        $tester = new CommandTester(new CarveConvertCommand($sources));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Would convert 1 description(s); skipped 1 with existing Carve and 1 with losses. Dry run', $tester->getDisplay());
        self::assertStringContainsString('product:' . str_repeat('c', 32), $tester->getDisplay());
        self::assertStringContainsString('element-dropped', $tester->getDisplay());
    }

    public function testWriteStoresOnlyLosslessConversionsWithoutExistingSource(): void
    {
        $sources = $this->sources();
        $sources->expects(self::once())->method('write')->with(
            self::callback(static fn (CarveStoredSource $target): bool => $target->id === str_repeat('a', 32)),
            "Hi *there*\n",
        );
        $tester = new CommandTester(new CarveConvertCommand($sources));

        self::assertSame(Command::SUCCESS, $tester->execute(['--write' => true]));
        self::assertStringContainsString('Converted 1 description(s)', $tester->getDisplay());
    }

    public function testOverwriteAndAcceptLossesWidenTheWrite(): void
    {
        $sources = $this->sources();
        $sources->expects(self::exactly(3))->method('write');
        $tester = new CommandTester(new CarveConvertCommand($sources));

        $tester->execute(['--write' => true, '--overwrite' => true, '--accept-losses' => true]);
        self::assertStringContainsString('Converted 3 description(s); skipped 0 with existing Carve and 0 with losses.', $tester->getDisplay());
    }

    public function testEntityFilterIsPassedAndValidated(): void
    {
        $sources = $this->createMock(CarveStoredSources::class);
        $sources->expects(self::once())->method('descriptions')->with(['category'])->willReturn([]);
        self::assertSame(Command::SUCCESS, (new CommandTester(new CarveConvertCommand($sources)))->execute(['--entity' => ['category']]));
        self::assertSame(Command::INVALID, (new CommandTester(new CarveConvertCommand($sources)))->execute(['--entity' => ['cms']]));
    }

    public function testAFileConvertsToStdout(): void
    {
        $file = sys_get_temp_dir() . '/carve-convert-' . bin2hex(random_bytes(4)) . '.md';
        file_put_contents($file, "Some **bold** text.\n");
        $tester = new CommandTester(new CarveConvertCommand($this->createStub(CarveStoredSources::class)));
        $status = $tester->execute(['path' => $file]);
        unlink($file);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame("Some *bold* text.\n", $tester->getDisplay());
    }

    /**
     * @return \MarkupCarve\Shopware\Service\CarveStoredSources&\PHPUnit\Framework\MockObject\MockObject
     */
    private function sources(): CarveStoredSources
    {
        $sources = $this->createMock(CarveStoredSources::class);
        $language = str_repeat('f', 32);
        $sources->method('descriptions')->willReturn([
            ['source' => new CarveStoredSource('product', str_repeat('a', 32), $language, 'carve_body', '<p>Hi <b>there</b></p>'), 'carve' => null],
            ['source' => new CarveStoredSource('product', str_repeat('b', 32), $language, 'carve_body', '<p>Old</p>'), 'carve' => "Kept.\n"],
            ['source' => new CarveStoredSource('product', str_repeat('c', 32), $language, 'carve_body', '<p>Clip</p><video src="a.mp4"></video>'), 'carve' => null],
        ]);

        return $sources;
    }
}
