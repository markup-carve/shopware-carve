<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads and writes the Carve source stored in the live version of products,
 * categories, manufacturers and static Carve CMS elements.
 */
class CarveStoredSources
{
    /**
     * Entity key => [translation table, id column, version column, custom field, DAL entity].
     *
     * @var array<string, array{string, string, string, string, string}>
     */
    public const FIELDS = [
        'product' => ['product_translation', 'product_id', 'product_version_id', 'carve_body', 'product'],
        'category' => ['category_translation', 'category_id', 'category_version_id', 'carve_category_body', 'category'],
        'manufacturer' => ['product_manufacturer_translation', 'product_manufacturer_id', 'product_manufacturer_version_id', 'carve_manufacturer_body', 'product_manufacturer'],
    ];

    /**
     * @var list<string>
     */
    public const ENTITIES = ['product', 'category', 'manufacturer', 'cms'];

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param array<string, \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>>> $repositories DAL entity name => repository
     */
    public function __construct(private readonly Connection $connection, private readonly array $repositories = [])
    {
    }

    /**
     * @param list<string> $entities
     *
     * @return iterable<\MarkupCarve\Shopware\Service\CarveStoredSource>
     */
    public function sources(array $entities): iterable
    {
        foreach ($entities as $entity) {
            if ($entity === 'cms') {
                yield from $this->cmsSources();

                continue;
            }
            [$table, $idColumn, $versionColumn, $field] = self::field($entity);
            $path = '$.' . $field;
            $query = "SELECT LOWER(HEX($idColumn)) AS id, LOWER(HEX(language_id)) AS language, JSON_UNQUOTE(JSON_EXTRACT(custom_fields, :path)) AS source"
                . " FROM $table WHERE $versionColumn = :version AND JSON_TYPE(JSON_EXTRACT(custom_fields, :path)) = 'STRING'"
                . " ORDER BY $idColumn, language_id";
            foreach ($this->connection->iterateAssociative($query, ['path' => $path, 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]) as $row) {
                yield new CarveStoredSource($entity, (string)$row['id'], (string)$row['language'], $field, (string)$row['source']);
            }
        }
    }

    /**
     * The HTML descriptions next to each Carve field, with the Carve source already stored there.
     *
     * @param list<string> $entities
     *
     * @return iterable<array{source: \MarkupCarve\Shopware\Service\CarveStoredSource, carve: string|null}>
     */
    public function descriptions(array $entities): iterable
    {
        foreach ($entities as $entity) {
            [$table, $idColumn, $versionColumn, $field] = self::field($entity);
            $query = "SELECT LOWER(HEX($idColumn)) AS id, LOWER(HEX(language_id)) AS language, description,"
                . ' JSON_UNQUOTE(JSON_EXTRACT(custom_fields, :path)) AS carve'
                . " FROM $table WHERE $versionColumn = :version AND description IS NOT NULL AND description <> ''"
                . " ORDER BY $idColumn, language_id";
            foreach ($this->connection->iterateAssociative($query, ['path' => '$.' . $field, 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]) as $row) {
                $carve = $row['carve'] ?? null;

                yield [
                    'source' => new CarveStoredSource($entity, (string)$row['id'], (string)$row['language'], $field, (string)$row['description']),
                    'carve' => is_string($carve) && $carve !== 'null' ? $carve : null,
                ];
            }
        }
    }

    /**
     * Stores Carve source through the DAL, so indexing and cache invalidation run as for an admin save.
     *
     * @throws \InvalidArgumentException
     */
    public function write(CarveStoredSource $target, string $carve, Context $context): void
    {
        $entity = self::field($target->entity)[4];
        $repository = $this->repositories[$entity] ?? throw new InvalidArgumentException(sprintf('No repository for "%s".', $entity));
        $repository->update([
            [
                'id' => $target->id,
                'translations' => [$target->languageId => ['customFields' => [$target->field => $carve]]],
            ],
        ], $context);
    }

    /**
     * @return iterable<\MarkupCarve\Shopware\Service\CarveStoredSource>
     */
    private function cmsSources(): iterable
    {
        $query = 'SELECT LOWER(HEX(t.cms_slot_id)) AS id, LOWER(HEX(t.language_id)) AS language, t.config FROM cms_slot_translation t'
            . ' INNER JOIN cms_slot s ON s.id = t.cms_slot_id AND s.version_id = t.cms_slot_version_id'
            . " WHERE s.type = 'carve' AND t.cms_slot_version_id = :version ORDER BY t.cms_slot_id, t.language_id";
        foreach ($this->connection->iterateAssociative($query, ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]) as $row) {
            $source = self::staticContent($row['config'] ?? null);
            if ($source !== null) {
                yield new CarveStoredSource('cms', (string)$row['id'], (string)$row['language'], 'content', $source);
            }
        }
    }

    /**
     * The Carve source of a CMS slot config, or null when the content is mapped or absent.
     */
    public static function staticContent(mixed $config): ?string
    {
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        $content = is_array($config) ? ($config['content'] ?? null) : null;
        if (!is_array($content) || ($content['source'] ?? 'static') === 'mapped') {
            return null;
        }
        $value = $content['value'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array{string, string, string, string, string}
     */
    private static function field(string $entity): array
    {
        return self::FIELDS[$entity] ?? throw new InvalidArgumentException(sprintf('Unknown Carve entity "%s".', $entity));
    }
}
