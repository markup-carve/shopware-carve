<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Controller;

use MarkupCarve\Shopware\Controller\CarvePreviewController;
use MarkupCarve\Shopware\Core\Content\Cms\CarveCmsElementResolver;
use MarkupCarve\Shopware\Service\CarveIncludeGate;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Tests\Service\CarveIncludeTestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CarvePreviewControllerTest extends CarveIncludeTestCase
{
    public function testThePreviewMatchesThePersistedCmsRenderForTheSameIdentity(): void
    {
        $root = $this->makeRoot(['chapters/one.crv' => "Included.\n"]);
        $source = "# Page\n\n{{ chapters/one.crv }}\n";

        foreach ([$this->privilegedAdmin(), $this->cmsOnlyAdmin(), Context::createDefaultContext()] as $context) {
            $renderer = $this->makeRenderer($root);
            $preview = (new CarvePreviewController($renderer))
                ->preview(new Request([], ['source' => $source]), $context);
            /** @var array{html: string} $payload */
            $payload = json_decode((string)$preview->getContent(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame($this->cmsHtml($renderer, $source, $context), $payload['html']);
        }
    }

    public function testThePreviewExpandsOnlyForTheGatedIdentity(): void
    {
        $root = $this->makeRoot(['one.crv' => "Included.\n"]);
        $request = new Request([], ['source' => "{{ one.crv }}\n"]);

        $granted = $this->decode((new CarvePreviewController($this->makeRenderer($root)))
            ->preview($request, $this->privilegedAdmin()));
        $refused = $this->decode((new CarvePreviewController($this->makeRenderer($root)))
            ->preview($request, $this->cmsOnlyAdmin()));

        self::assertTrue($granted['expanded']);
        self::assertStringContainsString('Included.', $granted['html']);
        self::assertFalse($refused['expanded']);
        self::assertStringContainsString('{{ one.crv }}', $refused['html']);
    }

    public function testThePersistedCmsRenderCarriesItsDependencies(): void
    {
        $root = $this->makeRoot(['one.crv' => "Included.\n"]);
        $slot = $this->resolveSlot($this->makeRenderer($root), "{{ one.crv }}\n", $this->privilegedAdmin());

        self::assertSame(
            [['path' => 'one.crv', 'resolved' => true]],
            $this->slotData($slot)['carveIncludeDependencies'],
        );
    }

    public function testOnlyStaticCmsSourceExpandsIncludes(): void
    {
        $root = $this->makeRoot(['secret.crv' => 'Do not expand']);
        foreach (['default', 'product_stream', 'unknown'] as $sourceType) {
            $slot = $this->resolveSlot($this->makeRenderer($root), '{{ secret.crv }}', $this->privilegedAdmin(), $sourceType);
            self::assertStringContainsString('{{ secret.crv }}', $this->slotData($slot)['html']);
            self::assertSame([], $this->slotData($slot)['carveIncludeDependencies']);
        }
    }

    public function testJsonPreviewUsesSourceAndReturnsDiagnostics(): void
    {
        $request = new Request(content: json_encode(['source' => '**Markdown bold**'], JSON_THROW_ON_ERROR));
        $request->headers->set('Content-Type', 'application/json');
        $response = (new CarvePreviewController($this->makeRenderer(null)))->preview($request, $this->cmsOnlyAdmin());
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Markdown bold', $payload['html']);
        self::assertNotEmpty($payload['diagnostics']);
    }

    public function testImportReturnsConvertedSourceWithoutPersistence(): void
    {
        $request = new Request(content: '{"source":"<p><strong>Imported</strong></p>","format":"html"}');
        $request->headers->set('Content-Type', 'application/json');
        $response = (new CarvePreviewController($this->makeRenderer(null)))->import($request, $this->cmsOnlyAdmin());
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('*Imported*', $payload['source']);
        self::assertArrayHasKey('diagnostics', $payload['report']);
    }

    public function testOversizedPreviewIsRejectedBeforeParsing(): void
    {
        $this->expectException(HttpException::class);
        (new CarvePreviewController($this->makeRenderer(null)))->preview(
            new Request(content: str_repeat('a', 262145)),
            $this->cmsOnlyAdmin(),
        );
    }

    public function testCmsReadPermissionDoesNotGrantLibraryAccess(): void
    {
        $this->expectException(MissingPrivilegeException::class);
        (new CarvePreviewController($this->makeRenderer(null)))->includes($this->cmsOnlyAdmin());
    }

    public function testLibraryOnlyListsContainedCarveFiles(): void
    {
        $root = $this->makeRoot(['en-GB/shared.crv' => 'Shared', 'ignored.txt' => 'ignored']);
        symlink($this->makeOutsideFile('secret'), $root . '/outside.crv');
        $gate = new CarveIncludeGate($this->makeConfig($root));
        $response = (new CarvePreviewController($this->makeRenderer($root), $gate))->includes($this->privilegedAdmin());
        self::assertSame(['paths' => ['en-GB/shared.crv'], 'limit' => 500], json_decode((string)$response->getContent(), true));
    }

    public function testMappedCmsContentReadsEntityValueAndKeepsIncludesLiteral(): void
    {
        $root = $this->makeRoot(['one.crv' => 'Do not expand']);
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('mapped-slot');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'),
        ]));
        $entity = new ProductEntity();
        $entity->setDescription('*Mapped* {{ one.crv }}');
        $salesChannelContext = $this->createStub(SalesChannelContext::class);
        $resolverContext = new EntityResolverContext(
            $salesChannelContext,
            new Request(),
            new ProductDefinition(),
            $entity,
        );
        (new CarveCmsElementResolver($this->makeRenderer($root)))->enrich($slot, $resolverContext, new ElementDataCollection());
        self::assertStringContainsString('<strong>Mapped</strong>', $this->slotData($slot)['html']);
        self::assertStringContainsString('{{ one.crv }}', $this->slotData($slot)['html']);
        self::assertStringNotContainsString('Do not expand', $this->slotData($slot)['html']);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\JsonResponse $response
     *
     * @return array{html: string, expanded: bool}
     */
    private function decode(object $response): array
    {
        /** @var array{html: string, expanded: bool} $payload */
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function cmsHtml(CarveRenderer $renderer, string $source, Context $context): string
    {
        return (string)$this->slotData($this->resolveSlot($renderer, $source, $context))['html'];
    }

    /**
     * @return array<string, mixed>
     */
    private function slotData(CmsSlotEntity $slot): array
    {
        $data = $slot->getData();
        self::assertInstanceOf(ArrayStruct::class, $data);

        return $data->all();
    }

    private function resolveSlot(CarveRenderer $renderer, string $source, Context $context, string $sourceType = FieldConfig::SOURCE_STATIC): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('content', $sourceType, $source),
        ]));

        $salesChannelContext = $this->createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $resolverContext = $this->createStub(ResolverContext::class);
        $resolverContext->method('getSalesChannelContext')->willReturn($salesChannelContext);

        (new CarveCmsElementResolver($renderer))->enrich($slot, $resolverContext, new ElementDataCollection());

        return $slot;
    }
}
