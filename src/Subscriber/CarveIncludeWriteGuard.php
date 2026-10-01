<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Subscriber;

use Doctrine\DBAL\Connection;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Transform\IncludeContext;
use MarkupCarve\Carve\Transform\IncludeExpander;
use MarkupCarve\Carve\Transform\IncludeResolverInterface;
use MarkupCarve\Carve\Transform\ResolvedInclude;
use MarkupCarve\Shopware\Service\CarveConverterFactory;
use MarkupCarve\Shopware\Service\CarveIncludeGate;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CarveIncludeWriteGuard implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection, private readonly ?CarveConverterFactory $factory = null)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validate'];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->isAllowed(CarveIncludeGate::PRIVILEGE)) {
            return;
        }
        $types = [];
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== 'cms_slot') {
                continue;
            }
            $payload = $command->getPayload();
            $id = $command->getPrimaryKey()['id'] ?? null;
            if (!is_string($id) || !isset($payload['type'])) {
                continue;
            }
            $types[bin2hex($id)] = $payload['type'] === 'carve' ? 'carve' : ($types[bin2hex($id)] ?? $payload['type']);
            // Retyping an existing slot can activate directives in old translations or overrides.
            if (
                $payload['type'] === 'carve'
                && $this->connection->fetchOne('SELECT type FROM cms_slot WHERE id = :id', ['id' => $id]) !== 'carve'
            ) {
                $this->validateStoredSlot($id);
            }
        }
        foreach ($event->getCommands() as $command) {
            $entity = $command->getEntityName();
            $payload = $command->getPayload();
            $storage = $command instanceof JsonUpdateCommand ? $command->getStorageName() : null;
            if ($entity === 'cms_slot_translation') {
                $id = $command->getPrimaryKey()['cms_slot_id'] ?? null;
                $config = $storage === 'config' ? $payload : ($payload['config'] ?? null);
                if (is_string($id) && $this->slotType(bin2hex($id), $types) === 'carve') {
                    $this->validateConfig($this->decode($config));
                }
            }
            if (in_array($entity, ['product_translation', 'category_translation', 'landing_page_translation', 'sales_channel_translation'], true)) {
                foreach (['slot_config', 'home_slot_config'] as $key) {
                    $overrides = $this->decode($storage === $key ? $payload : ($payload[$key] ?? null));
                    foreach ($overrides as $slotId => $config) {
                        if (Uuid::isValid($slotId) && $this->slotType($slotId, $types) === 'carve') {
                            $this->validateConfig($this->decode($config));
                        }
                    }
                }
            }
        }
    }

    private function validateStoredSlot(string $id): void
    {
        foreach ($this->connection->fetchFirstColumn('SELECT config FROM cms_slot_translation WHERE cms_slot_id = :id', ['id' => $id]) as $config) {
            $this->validateConfig($this->decode($config));
        }
        $path = '$."' . bin2hex($id) . '"';
        foreach (['product_translation' => 'slot_config', 'category_translation' => 'slot_config', 'landing_page_translation' => 'slot_config', 'sales_channel_translation' => 'home_slot_config'] as $table => $column) {
            $query = "SELECT JSON_EXTRACT($column, :path) FROM $table WHERE JSON_CONTAINS_PATH($column, 'one', :path) = 1";
            foreach ($this->connection->fetchFirstColumn($query, ['path' => $path]) as $config) {
                $this->validateConfig($this->decode($config));
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws \Shopware\Core\Framework\Api\Exception\MissingPrivilegeException
     */
    private function validateConfig(array $config): void
    {
        $content = $config['content'] ?? null;
        if (!is_array($content) || ($content['source'] ?? 'static') === 'mapped') {
            return;
        }
        $source = $content['value'] ?? null;
        if (is_string($source) && self::hasIncludes($source, $this->factory?->create())) {
            throw new MissingPrivilegeException([CarveIncludeGate::PRIVILEGE]);
        }
    }

    public static function hasIncludes(string $source, ?CarveConverter $converter = null): bool
    {
        if (!str_contains($source, '{{')) {
            return false;
        }
        $probe = new class implements IncludeResolverInterface {
            public bool $found = false;

            public function resolve(string $path, IncludeContext $context): ResolvedInclude|string|null
            {
                $this->found = true;

                return null;
            }
        };
        $converter ??= new CarveConverter();
        $converter->transform($converter->parse($source), new IncludeExpander(resolver: $probe, source: $source));

        return $probe->found;
    }

    /**
     * @param string $id
     * @param array<string, mixed> $types
     */
    private function slotType(string $id, array $types): mixed
    {
        return $types[$id] ?? $this->connection->fetchOne("SELECT type FROM cms_slot WHERE id = :id AND type = 'carve'", ['id' => Uuid::fromHexToBytes($id)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $config): array
    {
        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        return is_array($config) ? $config : [];
    }
}
