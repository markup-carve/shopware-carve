<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use Doctrine\DBAL\Connection;
use MarkupCarve\Shopware\Service\CarveFieldEditor;
use PHPUnit\Framework\TestCase;

class CarveFieldEditorTest extends TestCase
{
    public function testActivationRoundTripPreservesLabelsAndOtherPluginsEditors(): void
    {
        $fields = [
            'carve_body' => ['componentName' => 'sw-textarea-field', 'label' => ['de-DE' => 'Inhalt']],
            'carve_category_body' => ['componentName' => 'sw-textarea-field', 'label' => ['en-GB' => 'Category']],
            'carve_manufacturer_body' => ['componentName' => 'another-editor', 'label' => ['en-GB' => 'Brand']],
        ];
        $original = $fields;
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params) use (&$fields): string {
            return json_encode($fields[$params['name']], JSON_THROW_ON_ERROR);
        });
        $connection->expects(self::exactly(4))->method('executeStatement')->willReturnCallback(static function (string $sql, array $params) use (&$fields): int {
            self::assertSame('UPDATE custom_field SET config = :config WHERE name = :name', $sql);
            $fields[$params['name']] = json_decode($params['config'], true, 512, JSON_THROW_ON_ERROR);

            return 1;
        });
        CarveFieldEditor::configure($connection, true);
        self::assertSame('carve-editor', $fields['carve_body']['componentName']);
        self::assertSame(['de-DE' => 'Inhalt'], $fields['carve_body']['label']);
        self::assertSame($original['carve_manufacturer_body'], $fields['carve_manufacturer_body']);
        CarveFieldEditor::configure($connection, false);
        self::assertSame($original, $fields);
    }
}
