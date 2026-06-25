<?php declare(strict_types=1);

namespace Carve\Shopware\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Adds a `carve_manufacturer_body` text custom field to product_manufacturer entities
 * (reusing the `carve` set created by the product-field migration).
 *
 * Shopware has no dedicated storefront manufacturer page. The field is intended for
 * use in CMS layouts and theme templates where the brand/manufacturer is displayed.
 * Theme devs place `{{ product.manufacturer.translated.customFields.carve_manufacturer_body|carve }}`
 * (or the |carve_ctx variant for :product[SKU] references) at the desired render point.
 */
class Migration1782300200AddCarveManufacturerField extends MigrationStep
{
    private const SET_NAME = 'carve';
    private const FIELD_NAME = 'carve_manufacturer_body';

    public function getCreationTimestamp(): int
    {
        return 1782300200;
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
            ['sid' => $setId, 'e' => 'product_manufacturer']
        );
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        if ($hasRelation === false) {
            $connection->insert('custom_field_set_relation', [
                'id' => Uuid::randomBytes(),
                'set_id' => $setId,
                'entity_name' => 'product_manufacturer',
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
                'label' => ['en-GB' => 'Carve body (manufacturer)', 'de-DE' => 'Carve-Inhalt (Hersteller)'],
                'componentName' => 'sw-textarea-field',
                'customFieldType' => 'text',
                'customFieldPosition' => 3,
                'helpText' => [
                    'en-GB' => 'Carve markup for the manufacturer/brand. Render via |carve in your theme template where the brand is displayed.',
                    'de-DE' => 'Carve-Markup für den Hersteller/die Marke. Im Theme-Template via |carve rendern, wo die Marke angezeigt wird.',
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
