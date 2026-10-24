<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Command;

use MarkupCarve\Carve\Lint\IncludeLinter;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Service\CarveStoredSources;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'carve:lint', description: 'Lint Carve stored in products, categories, manufacturers and CMS elements, or Carve files')]
class CarveLintCommand extends Command
{
    public function __construct(private readonly CarveRenderer $renderer, private readonly CarveStoredSources $sources)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('paths', InputArgument::IS_ARRAY, 'Carve files to lint instead of the stored fields');
        $this->addOption('entity', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limit stored fields to: ' . implode(', ', CarveStoredSources::ENTITIES));
        $this->addOption('include-root', null, InputOption::VALUE_REQUIRED, 'Also lint what include directives in the given files pull in from below this root');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        /** @var list<string> $entities */
        $entities = $input->getOption('entity') ?: CarveStoredSources::ENTITIES;
        $unknown = array_diff($entities, CarveStoredSources::ENTITIES);
        if ($unknown !== []) {
            $output->writeln(sprintf('<error>Unknown entity "%s". Use: %s.</error>', implode('", "', $unknown), implode(', ', CarveStoredSources::ENTITIES)));

            return Command::INVALID;
        }

        $resolver = null;
        $root = $input->getOption('include-root');
        if (is_string($root) && $root !== '') {
            $real = realpath($root);
            if ($paths === [] || $real === false || !is_dir($real)) {
                $output->writeln('<error>--include-root needs file paths and an existing directory.</error>');

                return Command::INVALID;
            }
            $resolver = new FilesystemIncludeResolver($real);
        }

        $failed = false;
        $rows = [];
        $checked = 0;
        foreach ($this->inputs($paths, $entities, $failed, $output) as $location => $source) {
            $checked++;
            $warnings = $resolver === null ? $this->renderer->lint($source)
                : (new IncludeLinter())->lint($source, [], $resolver, realpath($location) ?: $location);
            foreach ($warnings as $warning) {
                $at = ($warning->file ?? $location) . ':' . $warning->line . ':' . $warning->column;
                // A file both linted and included by another one would report twice.
                $rows[$at . "\0" . $warning->rule . "\0" . $warning->message] = [$at, $warning->rule, $warning->message];
            }
        }
        $rows = array_values($rows);

        if ($rows === []) {
            $output->writeln(sprintf('No Carve problems found in %d source(s).', $checked));

            return $failed ? Command::FAILURE : Command::SUCCESS;
        }
        (new Table($output))->setHeaders(['Location', 'Rule', 'Message'])->setRows($rows)->render();
        $output->writeln(sprintf('<error>%d Carve problem(s) found in %d source(s).</error>', count($rows), $checked));

        return Command::FAILURE;
    }

    /**
     * @param list<string> $paths
     * @param list<string> $entities
     * @param bool $failed
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return iterable<string, string>
     */
    private function inputs(array $paths, array $entities, bool &$failed, OutputInterface $output): iterable
    {
        if ($paths === []) {
            foreach ($this->sources->sources($entities) as $stored) {
                yield $stored->label() => $stored->source;
            }

            return;
        }
        foreach ($paths as $path) {
            $source = is_file($path) ? @file_get_contents($path) : false;
            if ($source === false) {
                $failed = true;
                $output->writeln(sprintf('<error>File "%s" is not readable.</error>', $path));

                continue;
            }

            yield $path => $source;
        }
    }
}
