<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Command;

use MarkupCarve\Shopware\Service\CarveImport;
use MarkupCarve\Shopware\Service\CarveStoredSources;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Migrates the HTML descriptions of products, categories and manufacturers into
 * their Carve fields. Without --write it only reports what it would do.
 */
#[AsCommand(name: 'carve:convert', description: 'Convert stored HTML descriptions (or a Markdown or HTML file) into Carve')]
class CarveConvertCommand extends Command
{
    /**
     * @var list<string>
     */
    private const ENTITIES = ['product', 'category', 'manufacturer'];

    public function __construct(private readonly CarveStoredSources $sources)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::OPTIONAL, 'Convert this Markdown or HTML file to stdout instead of the stored descriptions');
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'markdown or html, for a file (guessed from its extension)');
        $this->addOption('entity', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limit to: ' . implode(', ', self::ENTITIES));
        $this->addOption('write', null, InputOption::VALUE_NONE, 'Store the converted Carve (default: dry run)');
        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Replace Carve fields that already hold source');
        $this->addOption('accept-losses', null, InputOption::VALUE_NONE, 'Also store conversions whose report has a warning');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        if (is_string($path) && $path !== '') {
            return $this->convertFile($path, $input, $output);
        }
        /** @var list<string> $entities */
        $entities = $input->getOption('entity') ?: self::ENTITIES;
        $unknown = array_diff($entities, self::ENTITIES);
        if ($unknown !== []) {
            $output->writeln(sprintf('<error>Unknown entity "%s". Use: %s.</error>', implode('", "', $unknown), implode(', ', self::ENTITIES)));

            return Command::INVALID;
        }
        $write = (bool)$input->getOption('write');
        $overwrite = (bool)$input->getOption('overwrite');
        $acceptLosses = (bool)$input->getOption('accept-losses');
        $context = Context::createCLIContext();
        $counts = ['converted' => 0, 'existing' => 0, 'lossy' => 0];

        foreach ($this->sources->descriptions($entities) as ['source' => $target, 'carve' => $existing]) {
            if ($existing !== null && trim($existing) !== '' && !$overwrite) {
                $counts['existing']++;
                $output->writeln(sprintf('%s: skipped, already has Carve source', $target->label()), OutputInterface::VERBOSITY_VERBOSE);

                continue;
            }
            $result = CarveImport::convert('html', $target->source);
            $lossy = $this->report($target->label(), $result['diagnostics'], $output);
            if ($lossy && !$acceptLosses) {
                $counts['lossy']++;

                continue;
            }
            if ($write) {
                $this->sources->write($target, $result['source'], $context);
            }
            $counts['converted']++;
        }

        $output->writeln(sprintf(
            '%s %d description(s); skipped %d with existing Carve and %d with losses.%s',
            $write ? 'Converted' : 'Would convert',
            $counts['converted'],
            $counts['existing'],
            $counts['lossy'],
            $write ? '' : ' Dry run: pass --write to store.',
        ));

        return Command::SUCCESS;
    }

    /**
     * Prints the diagnostics and says whether any of them loses content.
     *
     * @param string $label
     * @param array<\MarkupCarve\Carve\Diagnostics\Diagnostic> $diagnostics
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    private function report(string $label, array $diagnostics, OutputInterface $output): bool
    {
        $lossy = false;
        foreach ($diagnostics as $diagnostic) {
            $lossy = $lossy || $diagnostic->severity === 'warning' || $diagnostic->severity === 'error';
            $output->writeln(
                sprintf('%s: %s %s: %s', $label, $diagnostic->severity, $diagnostic->code, $diagnostic->message),
                $diagnostic->severity === 'info' ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL,
            );
        }

        return $lossy;
    }

    private function convertFile(string $path, InputInterface $input, OutputInterface $output): int
    {
        $from = $input->getOption('from') ?: match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'md', 'markdown' => 'markdown',
            'html', 'htm' => 'html',
            default => null,
        };
        $source = is_file($path) ? @file_get_contents($path) : false;
        if ($source === false) {
            $output->writeln(sprintf('<error>File "%s" is not readable.</error>', $path));

            return Command::FAILURE;
        }
        if (!in_array($from, ['markdown', 'html'], true)) {
            $output->writeln('<error>Cannot tell the input format. Pass --from=markdown or --from=html.</error>');

            return Command::FAILURE;
        }
        try {
            $result = CarveImport::convert($from, $source);
        } catch (RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
        $output->write($result['source'], false, OutputInterface::OUTPUT_RAW);
        $this->report($path, $result['diagnostics'], $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        return Command::SUCCESS;
    }
}
