<?php declare(strict_types=1);

namespace Carve\Shopware\Command;

use Carve\CarveConverter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders Carve source to one of four targets: HTML (default), Markdown, plain
 * text, or ANSI. `source` is a file path or `-` to read stdin / --text-input.
 * Demonstrates Carve's write-once-show-anywhere multi-target rendering.
 */
#[AsCommand(name: 'carve:render', description: 'Render Carve source to HTML/Markdown/plain/ANSI')]
class CarveRenderCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('source', InputArgument::REQUIRED, 'File path, or - for stdin');
        $this->addOption('text-input', null, InputOption::VALUE_REQUIRED, 'Inline source instead of a file (for - )');
        $this->addOption('html', null, InputOption::VALUE_NONE, 'HTML output (default)');
        $this->addOption('md', null, InputOption::VALUE_NONE, 'Markdown output');
        $this->addOption('plain', null, InputOption::VALUE_NONE, 'Plain-text output');
        $this->addOption('ansi', null, InputOption::VALUE_NONE, 'ANSI terminal output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $src = $this->readSource($input);

        $converter = match (true) {
            (bool) $input->getOption('md') => CarveConverter::markdown(),
            (bool) $input->getOption('plain') => CarveConverter::plainText(),
            (bool) $input->getOption('ansi') => CarveConverter::ansi(),
            default => new CarveConverter(safeMode: true),
        };

        $output->write($converter->convert($src));

        return Command::SUCCESS;
    }

    private function readSource(InputInterface $input): string
    {
        $source = (string) $input->getArgument('source');
        $inline = $input->getOption('text-input');
        if (is_string($inline)) {
            return $inline;
        }
        if ($source === '-') {
            return (string) file_get_contents('php://stdin');
        }

        return (string) file_get_contents($source);
    }
}
