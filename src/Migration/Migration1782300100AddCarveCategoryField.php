<?php declare(strict_types=1);

namespace Carve\Shopware\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Adds a `carve_body` text custom field to categories (reusing the `carve` set
 * created by the product-field migration) and relates the set to `category`.
 */
class Migration1782300100AddCarveCategoryField extends MigrationStep
{
    private const SET_NAME = 'carve';
    private const FIELD_NAME = 'carve_category_body';

    public function getCreationTimestamp(): int
    {
        return 1782300100;
    }

    public function update(Connection $connection): void
    {
        $setId = $connection->fetchOne(
            'SELECT id FROM custom_field_set WHERE name = :name',
            ['name' => self::SET_NAME]
        );
        if ($setId === false) {
            return; // product migration must run first; ordering guaranteed by timestamp
        }

        $hasRelation = $connection->fetchOne(
            'SELECT id FROM custom_field_set_relation WHERE set_id = :sid AND entity_name = :e',
            ['sid' => $setId, 'e' => 'category']
        );
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        if ($hasRelation === false) {
            $connection->insert('custom_field_set_relation', [
                'id' => Uuid::randomBytes(),
                'set_id' => $setId,
                'entity_name' => 'category',
                'created_at' => $now,
            ]);
        }

        $existing = $connection->fetchOne(
            'SELECT id FROM custom_field WHERE name = :name',
            ['name' => self::FIELD_NAME]
        );
        if ($existing !== false) {
            return;
        }

        $connection->insert('custom_field', [
            'id' => Uuid::randomBytes(),
            'name' => self::FIELD_NAME,
            'type' => 'text',
            'config' => json_encode([
                'label' => ['en-GB' => 'Carve body (category)', 'de-DE' => 'Carve-Inhalt (Kategorie)'],
                'componentName' => 'sw-field',
                'customFieldType' => 'textEditor',
                'customFieldPosition' => 2,
                'helpText' => [
                    'en-GB' => 'Carve markup rendered to safe HTML on the category page.',
                    'de-DE' => 'Carve-Markup, auf der Kategorieseite als sicheres HTML gerendert.',
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
