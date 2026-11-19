<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Command;

use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Include\FileResolver;
use MarkupCarve\Carve\Include\IncludeResolver;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Service\CarveWriterUnavailable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders Carve source to one of four targets: HTML (default), Markdown, plain
 * text, or ANSI. `source` is a file path or `-` to read stdin / --text-input.
 * Demonstrates Carve's write-once-show-anywhere multi-target rendering.
 *
 * Whoever runs the command already has shell access, so this is one of the two
 * contexts that may resolve files. A FILE input roots inclusion at its own
 * directory; stdin has no path context and so no inferable root.
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
        // Note: --ansi/--no-ansi are reserved by Symfony's console globally, so the
        // ANSI render target is exposed as --term to avoid an option-name collision.
        $this->addOption('term', null, InputOption::VALUE_NONE, 'ANSI terminal output (colored)');
        $this->addOption('include-root', null, InputOption::VALUE_REQUIRED, 'Containment root for include directives');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $src = $this->readSource($input);
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $target = match (true) {
            (bool)$input->getOption('md') => 'markdown',
            (bool)$input->getOption('plain') => 'text',
            (bool)$input->getOption('term') => 'ansi',
            default => 'html',
        };
        if ($target !== 'html') {
            try {
                $output->write(CarveRenderer::write($target, $src));
            } catch (CarveWriterUnavailable $exception) {
                $errors->writeln('<error>' . $exception->getMessage() . '</error>');

                return Command::FAILURE;
            }

            return Command::SUCCESS;
        }

        $includes = $this->buildIncludes($input);
        if ($includes === null) {
            $output->write(Carve::render($src));

            return Command::SUCCESS;
        }

        $result = Carve::renderWithDiagnostics($src, ['includes' => $includes]);
        $output->write($result->html);

        // A refused directive renders as the literal text it is, which reads
        // like prose somebody typed, so silence would hide a real error.
        foreach ($result->diagnostics as $diagnostic) {
            if (str_starts_with($diagnostic->code, 'include-')) {
                $errors->writeln($diagnostic->code . ': ' . $diagnostic->message);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * The flag is a path somebody typed on a command line, so it is expanded
     * against the working directory here, before the resolver sees it. A
     * relative directive resolves against the document's own directory, not
     * the root, so a widened root still reads a sibling the way the author
     * wrote it.
     *
     * @return array{resolver: \MarkupCarve\Carve\Include\IncludeResolver, file?: string}|null
     */
    private function buildIncludes(InputInterface $input): ?array
    {
        $source = $this->sourcePath($input);
        $flag = $input->getOption('include-root');
        if (is_string($flag) && $flag !== '') {
            $expanded = realpath($flag);
            $root = $expanded === false ? $flag : $expanded;
        } elseif ($source !== null) {
            $root = realpath(dirname($source));
            if ($root === false) {
                return null;
            }
        } else {
            return null;
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($source !== null && !str_starts_with($source, $prefix)) {
            // A relative directive resolves against the source's directory, which lies outside the root.
            return ['resolver' => new class implements IncludeResolver {
                public function resolve(array $request): array
                {
                    return ['error' => 'outside_root'];
                }
            }];
        }
        $includes = ['resolver' => FileResolver::forRoot($root)];
        if ($source !== null) {
            $includes['file'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($source, strlen($prefix)));
        }

        return $includes;
    }

    /**
     * The canonical path of a FILE input, or null when there is none: stdin and
     * `--text-input` have no path context and therefore no inferable root.
     */
    private function sourcePath(InputInterface $input): ?string
    {
        $source = (string)$input->getArgument('source');
        if ($source === '-' || is_string($input->getOption('text-input'))) {
            return null;
        }
        $real = realpath($source);

        return $real === false || !is_file($real) ? null : $real;
    }

    private function readSource(InputInterface $input): string
    {
        $source = (string)$input->getArgument('source');
        $inline = $input->getOption('text-input');
        if (is_string($inline)) {
            return $inline;
        }
        if ($source === '-') {
            return (string)file_get_contents('php://stdin');
        }

        return (string)file_get_contents($source);
    }
}
