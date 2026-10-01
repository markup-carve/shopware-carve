<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use Doctrine\DBAL\Connection;

class CarveFieldEditor
{
    public static function configure(Connection $connection, bool $enabled): void
    {
        $from = $enabled ? 'sw-textarea-field' : 'carve-editor';
        $to = $enabled ? 'carve-editor' : 'sw-textarea-field';
        foreach (['carve_body', 'carve_category_body', 'carve_manufacturer_body'] as $name) {
            $encoded = $connection->fetchOne('SELECT config FROM custom_field WHERE name = :name', ['name' => $name]);
            $config = is_string($encoded) ? json_decode($encoded, true, 512, JSON_THROW_ON_ERROR) : null;
            if (!is_array($config) || ($config['componentName'] ?? null) !== $from) {
                continue;
            }
            $config['componentName'] = $to;
            $connection->executeStatement('UPDATE custom_field SET config = :config WHERE name = :name', [
                'config' => json_encode($config, JSON_THROW_ON_ERROR),
                'name' => $name,
            ]);
        }
    }
}
