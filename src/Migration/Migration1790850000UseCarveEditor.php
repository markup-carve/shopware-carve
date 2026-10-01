<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Migration;

use Doctrine\DBAL\Connection;
use MarkupCarve\Shopware\Service\CarveFieldEditor;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1790850000UseCarveEditor extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790850000;
    }

    public function update(Connection $connection): void
    {
        $active = (bool)$connection->fetchOne('SELECT active FROM plugin WHERE name = :name', ['name' => 'ShopwareCarve']);
        CarveFieldEditor::configure($connection, $active);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
