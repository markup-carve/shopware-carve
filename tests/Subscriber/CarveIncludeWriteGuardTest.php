<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Subscriber;

use Doctrine\DBAL\Connection;
use MarkupCarve\Shopware\Service\CarveIncludeGate;
use MarkupCarve\Shopware\Subscriber\CarveIncludeWriteGuard;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;

class CarveIncludeWriteGuardTest extends TestCase
{
    public function testAnUnprivilegedEditorCannotStoreAnInclude(): void
    {
        $command = $this->command('cms_slot_translation', [
            'config' => json_encode(['content' => ['source' => 'static', 'value' => '{{ warranty.crv }}']]),
        ], $this->slotKey());
        $this->expectException(MissingPrivilegeException::class);
        $this->guard()->validate(new PreWriteValidationEvent(WriteContext::createFromContext($this->context()), [$command]));
    }

    public function testJsonUpdatesOfSlotConfigAreAlsoGated(): void
    {
        $command = $this->command('cms_slot_translation', [
            'content' => ['source' => 'static', 'value' => '{{ warranty.crv }}'],
        ], $this->slotKey(), 'config');
        $this->expectException(MissingPrivilegeException::class);
        $this->guard()->validate(new PreWriteValidationEvent(WriteContext::createFromContext($this->context()), [$command]));
    }

    public function testProductLayoutOverridesCannotBypassTheGate(): void
    {
        $command = $this->command('product_translation', [
            'slot_config' => json_encode([str_repeat('a', 32) => ['content' => ['source' => 'static', 'value' => '{{ warranty.crv }}']]]),
        ], ['product_id' => Uuid::randomBytes(), 'product_version_id' => Uuid::randomBytes(), 'language_id' => Uuid::randomBytes()]);
        $this->expectException(MissingPrivilegeException::class);
        $this->guard()->validate(new PreWriteValidationEvent(WriteContext::createFromContext($this->context()), [$command]));
    }

    public function testPrivilegeAllowsPublicationAndCodeSamplesRemainLiteral(): void
    {
        $command = $this->command('cms_slot_translation', [
            'config' => json_encode(['content' => ['source' => 'static', 'value' => '{{ warranty.crv }}']]),
        ], $this->slotKey());
        $this->guard()->validate(new PreWriteValidationEvent(WriteContext::createFromContext($this->context(true)), [$command]));
        self::assertTrue(CarveIncludeWriteGuard::hasIncludes('{{ warranty.crv }}'));
        self::assertFalse(CarveIncludeWriteGuard::hasIncludes("```\n{{ warranty.crv }}\n```"));
        self::assertFalse(CarveIncludeWriteGuard::hasIncludes('`{{ warranty.crv }}`'));
    }

    public function testDefaultSourceCannotBypassPublicationPrivilege(): void
    {
        $command = $this->command('cms_slot_translation', [
            'config' => json_encode(['content' => ['source' => 'default', 'value' => '{{ secret.crv }}']]),
        ], $this->slotKey());
        $this->expectException(MissingPrivilegeException::class);
        $this->guard()->validate(new PreWriteValidationEvent(WriteContext::createFromContext($this->context()), [$command]));
    }

    public function testUnknownOverrideSourceCannotBypassPublicationPrivilege(): void
    {
        $command = $this->command('category_translation', [
            'slot_config' => json_encode([str_repeat('a', 32) => ['content' => ['source' => 'unknown', 'value' => '{{ secret.crv }}']]]),
        ], ['category_id' => Uuid::randomBytes(), 'language_id' => Uuid::randomBytes()]);
        $this->expectException(MissingPrivilegeException::class);
        $this->guard()->validate(new PreWriteValidationEvent(WriteContext::createFromContext($this->context()), [$command]));
    }

    public function testRetypingHarmlessSlotDoesNotRequireIncludePrivilege(): void
    {
        $command = $this->command('cms_slot', ['type' => 'carve'], ['id' => Uuid::fromHexToBytes(str_repeat('a', 32))]);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('text');
        $connection->expects(self::exactly(5))->method('fetchFirstColumn')->willReturn([]);
        (new CarveIncludeWriteGuard($connection))->validate(new PreWriteValidationEvent(
            WriteContext::createFromContext($this->context()),
            [$command],
        ));
    }

    public function testNewSlotCannotActivateAnOrphanedIncludeOverride(): void
    {
        $command = $this->command('cms_slot', ['type' => 'carve'], ['id' => Uuid::fromHexToBytes(str_repeat('a', 32))]);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects(self::exactly(2))->method('fetchFirstColumn')->willReturnOnConsecutiveCalls(
            [],
            [json_encode(['content' => ['source' => 'static', 'value' => '{{ secret.crv }}']])],
        );
        $this->expectException(MissingPrivilegeException::class);
        (new CarveIncludeWriteGuard($connection))->validate(new PreWriteValidationEvent(
            WriteContext::createFromContext($this->context()),
            [$command],
        ));
    }

    /**
     * @param string $entity
     * @param array<string, mixed> $payload
     * @param array<string, string> $key
     * @param string|null $storage
     */
    private function command(string $entity, array $payload, array $key, ?string $storage = null): UpdateCommand
    {
        $command = $storage === null ? $this->createStub(UpdateCommand::class) : $this->createStub(JsonUpdateCommand::class);
        $command->method('getEntityName')->willReturn($entity);
        $command->method('getPayload')->willReturn($payload);
        $command->method('getPrimaryKey')->willReturn($key);
        if ($command instanceof JsonUpdateCommand) {
            $command->method('getStorageName')->willReturn($storage);
        }

        return $command;
    }

    private function guard(): CarveIncludeWriteGuard
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('carve');

        return new CarveIncludeWriteGuard($connection);
    }

    private function context(bool $privileged = false): Context
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions($privileged ? [CarveIncludeGate::PRIVILEGE] : ['cms_page:read', 'cms_page:update']);

        return new Context($source);
    }

    /**
     * @return array<string, string>
     */
    private function slotKey(): array
    {
        return ['cms_slot_id' => Uuid::fromHexToBytes(str_repeat('a', 32)), 'cms_slot_version_id' => Uuid::randomBytes(), 'language_id' => Uuid::randomBytes()];
    }
}
