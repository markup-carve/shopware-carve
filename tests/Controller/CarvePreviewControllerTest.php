<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Controller;

use MarkupCarve\Shopware\Controller\CarvePreviewController;
use MarkupCarve\Shopware\Core\Content\Cms\CarveCmsElementResolver;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Tests\Service\CarveIncludeTestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

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

    private function resolveSlot(CarveRenderer $renderer, string $source, Context $context): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('content', FieldConfig::SOURCE_STATIC, $source),
        ]));

        $salesChannelContext = $this->createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $resolverContext = $this->createStub(ResolverContext::class);
        $resolverContext->method('getSalesChannelContext')->willReturn($salesChannelContext);

        (new CarveCmsElementResolver($renderer))->enrich($slot, $resolverContext, new ElementDataCollection());

        return $slot;
    }
}
