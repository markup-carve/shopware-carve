<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Command;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MarkupCarve\Carve\CarveConverter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'carve:fmt', description: 'Print Carve files in canonical form, or rewrite or check them')]
class CarveFormatCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Carve files, or - for stdin');
        $this->addOption('write', null, InputOption::VALUE_NONE, 'Rewrite each file in place');
        $this->addOption('check', null, InputOption::VALUE_NONE, 'Exit unsuccessfully if a file is not formatted, without changing it');
        $this->addOption('align-tables', null, InputOption::VALUE_NONE, 'Pad pipe table cells so the columns line up');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $write = (bool)$input->getOption('write');
        $check = (bool)$input->getOption('check');
        if ($write && $check) {
            $output->writeln('<error>--write and --check are mutually exclusive.</error>');

            return Command::INVALID;
        }

        $failed = false;
        $unformatted = 0;
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        foreach ($paths as $path) {
            $stdin = $path === '-';
            if ($stdin && $write) {
                $output->writeln('<error>--write needs a file, not stdin.</error>');

                return Command::INVALID;
            }
            $source = $stdin ? file_get_contents('php://stdin') : (is_file($path) ? @file_get_contents($path) : false);
            if ($source === false) {
                $failed = true;
                $output->writeln(sprintf('<error>File "%s" is not readable.</error>', $path));

                continue;
            }
            $formatted = CarveConverter::toCarve($source, (bool)$input->getOption('align-tables'));
            if ($check) {
                if ($formatted !== $source) {
                    $unformatted++;
                    $output->writeln(sprintf('%s is not formatted', $path));
                }

                continue;
            }
            if ($write) {
                try {
                    if ($formatted !== $source) {
                        $filesystem = new Filesystem(new LocalFilesystemAdapter(dirname($path)));
                        $filesystem->write(basename($path), $formatted);
                    }
                } catch (FilesystemException) {
                    $failed = true;
                    $output->writeln(sprintf('<error>File "%s" could not be written.</error>', $path));
                }

                continue;
            }
            $output->write($formatted, false, OutputInterface::OUTPUT_RAW);
        }

        if ($unformatted > 0) {
            $output->writeln(sprintf('<error>%d file(s) not formatted.</error>', $unformatted));

            return Command::FAILURE;
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
