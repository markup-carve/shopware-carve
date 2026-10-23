<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Subscriber;

use Doctrine\DBAL\Connection;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Service\CarveStoredSources;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Rejects admin and API writes whose Carve source has lint findings, when the
 * `lintOnSave` setting is on. System writes (migrations, carve:convert) pass.
 */
class CarveLintWriteGuard implements EventSubscriberInterface
{
    /**
     * @var string
     */
    public const CONFIG_KEY = 'ShopwareCarve.config.lintOnSave';

    public function __construct(
        private readonly SystemConfigService $config,
        private readonly CarveRenderer $renderer,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validate'];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getSource() instanceof SystemSource || !$this->config->getBool(self::CONFIG_KEY)) {
            return;
        }
        $fields = [];
        foreach (CarveStoredSources::FIELDS as [, , , $field, $entity]) {
            $fields[$entity . '_translation'] = $field;
        }
        $types = [];
        foreach ($event->getCommands() as $command) {
            $payload = $command->getPayload();
            $id = $command->getPrimaryKey()['id'] ?? null;
            if ($command->getEntityName() === 'cms_slot' && is_string($id) && isset($payload['type'])) {
                $types[bin2hex($id)] = $payload['type'];
            }
        }
        foreach ($event->getCommands() as $command) {
            $entity = $command->getEntityName();
            $payload = $command->getPayload();
            $storage = $command instanceof JsonUpdateCommand ? $command->getStorageName() : null;
            if (isset($fields[$entity])) {
                $customFields = $this->decode($storage === 'custom_fields' ? $payload : ($payload['custom_fields'] ?? null));
                $source = $customFields[$fields[$entity]] ?? null;
                if (is_string($source)) {
                    $this->check($event, $command, $source, '/customFields/' . $fields[$entity]);
                }
            }
            if ($entity === 'cms_slot_translation') {
                $id = $command->getPrimaryKey()['cms_slot_id'] ?? null;
                $source = CarveStoredSources::staticContent($storage === 'config' ? $payload : ($payload['config'] ?? null));
                if (is_string($id) && $source !== null && $this->isCarveSlot($id, $types)) {
                    $this->check($event, $command, $source, '/config/content/value');
                }
            }
        }
    }

    private function check(PreWriteValidationEvent $event, WriteCommand $command, string $source, string $field): void
    {
        $violations = new ConstraintViolationList();
        foreach ($this->renderer->lint($source) as $warning) {
            // Fragment targets can live elsewhere on the storefront page.
            if ($warning->rule === 'broken-fragment-link') {
                continue;
            }
            $message = sprintf('Carve %s (%d:%d): %s', $warning->rule, $warning->line, $warning->column, $warning->message);
            $violations->add(new ConstraintViolation($message, $message, [], null, $field, $source, null, 'CARVE-LINT-' . $warning->rule));
        }
        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }

    /**
     * @param string $id
     * @param array<string, mixed> $types
     */
    private function isCarveSlot(string $id, array $types): bool
    {
        $hex = bin2hex($id);
        if (isset($types[$hex])) {
            return $types[$hex] === 'carve';
        }

        return $this->connection->fetchOne("SELECT type FROM cms_slot WHERE id = :id AND type = 'carve'", ['id' => Uuid::fromHexToBytes($hex)]) === 'carve';
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
