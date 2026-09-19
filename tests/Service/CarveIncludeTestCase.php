<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarveIncludeGate;
use MarkupCarve\Shopware\Service\CarveRenderer;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Temporary include trees and the three identities the gate distinguishes.
 */
abstract class CarveIncludeTestCase extends TestCase
{
    /**
     * @var list<string>
     */
    private array $roots = [];

    /**
     * @var list<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_link($file) || is_file($file)) {
                unlink($file);
            }
        }
        foreach ($this->roots as $root) {
            $this->removeTree($root);
        }
        $this->files = [];
        $this->roots = [];

        parent::tearDown();
    }

    /**
     * @param array<string, string> $tree Relative path to file contents.
     */
    protected function makeRoot(array $tree = []): string
    {
        $root = sys_get_temp_dir() . '/shopware-carve-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->roots[] = $root;
        foreach ($tree as $relative => $contents) {
            $target = $root . '/' . $relative;
            $directory = dirname($target);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($target, $contents);
        }

        return $root;
    }

    protected function makeOutsideFile(string $contents): string
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'shopware-carve-secret-');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    protected function makeRenderer(?string $includeRoot): CarveRenderer
    {
        return new CarveRenderer($this->makeConfig($includeRoot), new CarveIncludeGate($this->makeConfig($includeRoot)));
    }

    protected function makeConfig(?string $includeRoot): SystemConfigService
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(
            static fn (string $key): mixed => $key === CarveIncludeGate::CONFIG_KEY ? $includeRoot : null,
        );

        return $config;
    }

    /**
     * An administration identity holding the filesystem-include privilege.
     */
    protected function privilegedAdmin(): Context
    {
        $source = new AdminApiSource('user-id');
        $source->setPermissions([CarveIncludeGate::PRIVILEGE]);

        return new Context($source);
    }

    /**
     * An administration identity with full CMS rights and nothing else.
     */
    protected function cmsOnlyAdmin(): Context
    {
        $source = new AdminApiSource('user-id');
        $source->setPermissions(['cms_page:read', 'cms_page:update', 'cms_slot:update']);

        return new Context($source);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            if (is_link($child) || is_file($child)) {
                unlink($child);

                continue;
            }
            $this->removeTree($child);
        }
        rmdir($path);
    }
}
