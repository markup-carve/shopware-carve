<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware;

use Doctrine\DBAL\Connection;
use LogicException;
use MarkupCarve\Shopware\Service\CarveFieldEditor;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class ShopwareCarve extends Plugin
{
    public function activate(ActivateContext $activateContext): void
    {
        $this->configureEditor(true);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->configureEditor(false);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->configureEditor(false);
    }

    private function configureEditor(bool $enabled): void
    {
        $connection = $this->container?->get(Connection::class);
        if (!$connection instanceof Connection) {
            throw new LogicException('The plugin lifecycle needs the Shopware database connection.');
        }
        CarveFieldEditor::configure($connection, $enabled);
    }
}
