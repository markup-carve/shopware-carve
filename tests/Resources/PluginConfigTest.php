<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Resources;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Unit tests construct services by hand, so nothing else loads these files the way a shop does.
 */
class PluginConfigTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../src/Resources/config';

    public function testConfigXmlMatchesTheCoreSchema(): void
    {
        $schema = dirname((string)(new ReflectionClass(SystemConfigService::class))->getFileName()) . '/Schema/config.xsd';
        self::assertFileExists($schema);
        $document = new DOMDocument();
        $document->load(self::CONFIG_DIR . '/config.xml');
        $previous = libxml_use_internal_errors(true);
        $valid = $document->schemaValidate($schema);
        $errors = array_map(static fn ($error): string => trim($error->message) . ' (line ' . $error->line . ')', libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertTrue($valid, implode("\n", $errors));
    }

    public function testRoutesFileImportsTheControllerDirectory(): void
    {
        $file = self::CONFIG_DIR . '/routes.xml';
        self::assertFileExists($file);
        $document = new DOMDocument();
        $document->load($file);
        $imports = [];
        foreach ($document->getElementsByTagName('import') as $import) {
            self::assertSame('attribute', $import->getAttribute('type'));
            $imports[] = realpath(self::CONFIG_DIR . '/' . $import->getAttribute('resource'));
        }

        self::assertContains(realpath(__DIR__ . '/../../src/Controller'), $imports);
    }

    public function testReferencedCoreServicesExist(): void
    {
        $core = dirname((string)(new ReflectionClass(Kernel::class))->getFileName());
        $ids = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($core)) as $file) {
            if ($file->getExtension() === 'xml' && str_contains($file->getPath(), 'DependencyInjection')) {
                preg_match_all('/<service\s[^>]*\bid="([^"]+)"/', (string)file_get_contents($file->getPathname()), $matches);
                $ids += array_flip($matches[1]);
            }
        }
        if ($ids === []) {
            self::markTestSkipped('This core version does not define its services in XML.');
        }
        preg_match_all("/'@(Shopware\\\\[^']+)'/", (string)file_get_contents(self::CONFIG_DIR . '/services.yaml'), $matches);
        self::assertNotEmpty($matches[1]);
        $missing = array_values(array_filter(array_unique($matches[1]), static fn (string $id): bool => !isset($ids[$id])));

        self::assertSame([], $missing);
    }
}
