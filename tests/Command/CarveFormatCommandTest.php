<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Command;

use MarkupCarve\Shopware\Command\CarveFormatCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CarveFormatCommandTest extends TestCase
{
    /**
     * @var string
     */
    private const TABLE = "| a | bbb |\n|---|---|\n| cc | d |\n";

    private string $file;

    protected function setUp(): void
    {
        $this->file = (string)tempnam(sys_get_temp_dir(), 'carve-fmt-');
        file_put_contents($this->file, self::TABLE);
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    public function testAlignTablesPadsTheColumns(): void
    {
        $tester = new CommandTester(new CarveFormatCommand());
        $tester->execute(['paths' => [$this->file], '--align-tables' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString("| a  | bbb |\n", $tester->getDisplay());

        $plain = new CommandTester(new CarveFormatCommand());
        $plain->execute(['paths' => [$this->file]]);
        self::assertSame(self::TABLE, $plain->getDisplay());
    }

    public function testCheckFailsWithoutChangingTheFile(): void
    {
        $tester = new CommandTester(new CarveFormatCommand());
        $tester->execute(['paths' => [$this->file], '--check' => true, '--align-tables' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('is not formatted', $tester->getDisplay());
        self::assertSame(self::TABLE, file_get_contents($this->file));
    }

    public function testWriteRewritesTheFileInPlace(): void
    {
        $tester = new CommandTester(new CarveFormatCommand());
        $tester->execute(['paths' => [$this->file], '--write' => true, '--align-tables' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString("| a  | bbb |\n", (string)file_get_contents($this->file));
    }

    public function testWriteAndCheckAreExclusive(): void
    {
        $tester = new CommandTester(new CarveFormatCommand());

        self::assertSame(Command::INVALID, $tester->execute(['paths' => [$this->file], '--write' => true, '--check' => true]));
    }
}
