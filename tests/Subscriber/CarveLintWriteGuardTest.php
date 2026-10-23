<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Subscriber;

use Doctrine\DBAL\Connection;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Subscriber\CarveLintWriteGuard;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CarveLintWriteGuardTest extends TestCase
{
    public function testAProductFieldWithAFindingIsRejected(): void
    {
        $event = $this->validate('product_translation', ['custom_fields' => json_encode(['carve_body' => '**bold**'])]);
        $exceptions = $event->getExceptions()->getExceptions();
        self::assertCount(1, $exceptions);
        self::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        $violation = $exceptions[0]->getViolations()->get(0);
        self::assertSame('/customFields/carve_body', $violation->getPropertyPath());
        self::assertStringContainsString('markdown-strong-double-star', (string)$violation->getMessage());
    }

    public function testJsonUpdatesOfCustomFieldsAreChecked(): void
    {
        $event = $this->validate('product_manufacturer_translation', ['carve_manufacturer_body' => '**bold**'], storage: 'custom_fields');
        self::assertCount(1, $event->getExceptions()->getExceptions());
    }

    public function testCleanSourceAndOtherCustomFieldsPass(): void
    {
        self::assertCount(0, $this->validate('category_translation', ['custom_fields' => json_encode(['carve_category_body' => "A *strong* word.\n"])])->getExceptions()->getExceptions());
        self::assertCount(0, $this->validate('product_translation', ['custom_fields' => json_encode(['other' => '**bold**'])])->getExceptions()->getExceptions());
    }

    public function testFragmentLinksDoNotBlockASave(): void
    {
        self::assertCount(0, $this->validate('product_translation', ['custom_fields' => json_encode(['carve_body' => '[Reviews](#reviews)'])])->getExceptions()->getExceptions());
    }

    public function testStaticCarveSlotsAreCheckedAndOtherSlotsAreNot(): void
    {
        $config = json_encode(['content' => ['source' => 'static', 'value' => '**bold**']]);
        $key = ['cms_slot_id' => Uuid::randomBytes(), 'language_id' => Uuid::randomBytes()];
        self::assertCount(1, $this->validate('cms_slot_translation', ['config' => $config], $key, slotType: 'carve')->getExceptions()->getExceptions());
        self::assertCount(0, $this->validate('cms_slot_translation', ['config' => $config], $key, slotType: false)->getExceptions()->getExceptions());
    }

    public function testTheGateIsOffByDefaultAndSkipsSystemWrites(): void
    {
        $payload = ['custom_fields' => json_encode(['carve_body' => '**bold**'])];
        self::assertCount(0, $this->validate('product_translation', $payload, enabled: false)->getExceptions()->getExceptions());
        self::assertCount(0, $this->validate('product_translation', $payload, system: true)->getExceptions()->getExceptions());
    }

    /**
     * @param string $entity
     * @param array<string, mixed> $payload
     * @param array<string, string> $key
     * @param string|null $storage
     * @param string|false $slotType
     * @param bool $enabled
     * @param bool $system
     */
    private function validate(
        string $entity,
        array $payload,
        array $key = [],
        ?string $storage = null,
        string|false $slotType = 'carve',
        bool $enabled = true,
        bool $system = false,
    ): PreWriteValidationEvent {
        $command = $storage === null ? $this->createStub(UpdateCommand::class) : $this->createStub(JsonUpdateCommand::class);
        $command->method('getEntityName')->willReturn($entity);
        $command->method('getPayload')->willReturn($payload);
        $command->method('getPrimaryKey')->willReturn($key);
        $command->method('getPath')->willReturn('/0/translations/0');
        if ($command instanceof JsonUpdateCommand) {
            $command->method('getStorageName')->willReturn($storage);
        }
        $config = $this->createStub(SystemConfigService::class);
        $config->method('getBool')->willReturn($enabled);
        $config->method('get')->willReturn(null);
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($slotType);
        $context = new Context($system ? new SystemSource() : new AdminApiSource(Uuid::randomHex()));
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]);
        (new CarveLintWriteGuard($config, new CarveRenderer($config), $connection))->validate($event);

        return $event;
    }
}
