<?php declare(strict_types=1);

namespace Carve\Shopware\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Adds the `carve_body` text custom field (set `carve`) to products, so the
 * storefront can render authored Carve source through the |carve filter.
 */
class Migration1782300000AddCarveProductField extends MigrationStep
{
    private const SET_NAME = 'carve';
    private const FIELD_NAME = 'carve_body';

    public function getCreationTimestamp(): int
    {
        return 1782300000;
    }

    public function update(Connection $connection): void
    {
        $existing = $connection->fetchOne(
            'SELECT id FROM custom_field WHERE name = :name',
            ['name' => self::FIELD_NAME]
        );
        if ($existing !== false) {
            return;
        }

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $setId = $connection->fetchOne(
            'SELECT id FROM custom_field_set WHERE name = :name',
            ['name' => self::SET_NAME]
        );
        if ($setId === false) {
            $setId = Uuid::randomBytes();
            $connection->insert('custom_field_set', [
                'id' => $setId,
                'name' => self::SET_NAME,
                'config' => json_encode([
                    'label' => ['en-GB' => 'Carve', 'de-DE' => 'Carve'],
                ], JSON_THROW_ON_ERROR),
                'active' => 1,
                'position' => 1,
                'created_at' => $now,
            ]);
            $connection->insert('custom_field_set_relation', [
                'id' => Uuid::randomBytes(),
                'set_id' => $setId,
                'entity_name' => 'product',
                'created_at' => $now,
            ]);
        }

        $connection->insert('custom_field', [
            'id' => Uuid::randomBytes(),
            'name' => self::FIELD_NAME,
            'type' => 'text',
            'config' => json_encode([
                'label' => ['en-GB' => 'Carve body', 'de-DE' => 'Carve-Inhalt'],
                'componentName' => 'sw-textarea-field',
                'customFieldType' => 'text',
                'customFieldPosition' => 1,
                'helpText' => [
                    'en-GB' => 'Carve markup. Rendered to safe HTML on the storefront.',
                    'de-DE' => 'Carve-Markup. Wird im Storefront als sicheres HTML gerendert.',
                ],
            ], JSON_THROW_ON_ERROR),
            'active' => 1,
            'set_id' => $setId,
            'created_at' => $now,
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
