<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Command;

use MarkupCarve\Shopware\Service\CarveIncludeCache;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'carve:includes:invalidate', description: 'Invalidate pages that transclude deployed Carve files')]
class CarveInvalidateIncludesCommand extends Command
{
    public function __construct(private readonly CacheInvalidator $invalidator, private readonly CarveIncludeCache $cache)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('paths', InputArgument::IS_ARRAY, 'Changed paths relative to includeRoot; omit to invalidate all includes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        foreach ($paths as $path) {
            if (str_contains($path, '\\') || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
                $output->writeln('<error>Use paths relative to includeRoot without traversal.</error>');

                return Command::INVALID;
            }
        }
        $paths = array_map(static fn (string $path): string => implode('/', array_filter(explode('/', $path), static fn (string $part): bool => $part !== '' && $part !== '.')), $paths);
        $tags = $paths === [] ? [CarveIncludeCache::ALL] : array_map($this->cache->pathTag(...), $paths);
        $this->invalidator->invalidate($tags, true);
        $output->writeln('Invalidated Carve include dependencies.');

        return Command::SUCCESS;
    }
}
