<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Controller;

use FilesystemIterator;
use MarkupCarve\Carve\Converter\HtmlToCarve;
use MarkupCarve\Carve\Converter\MarkdownToCarve;
use MarkupCarve\Shopware\Service\CarveContextRenderer;
use MarkupCarve\Shopware\Service\CarveIncludeGate;
use MarkupCarve\Shopware\Service\CarveIncludeResult;
use MarkupCarve\Shopware\Service\CarveRenderer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use SplFileInfo;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class CarvePreviewController
{
    public function __construct(
        private readonly CarveRenderer $renderer,
        private readonly ?CarveIncludeGate $gate = null,
        private readonly ?AbstractSalesChannelContextFactory $contexts = null,
        private readonly ?CarveContextRenderer $contextRenderer = null,
        private readonly ?AbstractTranslator $translator = null,
        private readonly ?LanguageLocaleCodeProvider $locales = null,
    ) {
    }

    #[Route(path: '/api/_action/carve/preview', name: 'api.action.carve.preview', methods: ['POST'])]
    public function preview(Request $request, Context $context): JsonResponse
    {
        $this->authorize($context);
        $source = $this->source($request);
        $payload = $request->getPayload();
        $channelId = $payload->get('salesChannelId');
        $namespace = $payload->get('namespace');
        $namespace = is_string($namespace) ? $namespace : null;
        $includes = $payload->getBoolean('includes', true);
        $diagnosticChannelId = null;
        $locale = null;
        if (is_string($channelId) && $channelId !== '' && $this->contexts !== null && $this->contextRenderer !== null) {
            if (!$context->isAllowed('sales_channel:read')) {
                throw new MissingPrivilegeException(['sales_channel:read']);
            }
            if (!$context->isAllowed('product:read')) {
                throw new MissingPrivilegeException(['product:read']);
            }
            if (!Uuid::isValid($channelId)) {
                throw new BadRequestHttpException('Invalid sales channel ID.');
            }
            try {
                $previewContext = $this->contexts->create(Uuid::randomHex(), $channelId, [
                    SalesChannelContextService::LANGUAGE_ID => $this->languageId($request, $context),
                ]);
            } catch (ShopwareHttpException $exception) {
                if ($exception->getErrorCode() === SalesChannelException::LANGUAGE_INVALID_EXCEPTION) {
                    throw new BadRequestHttpException('The selected language is not available for this sales channel.', $exception);
                }

                throw $exception;
            }
            $diagnosticChannelId = $previewContext->getSalesChannelId();
            $locale = $this->locales?->getLocaleForLanguageId($previewContext->getLanguageId());
            // The guest channel context supplies prices, but the acting admin decides file access.
            $includes = $includes && ($this->gate?->isGranted($context) ?? false);
            if ($this->translator !== null && $this->locales !== null) {
                $this->translator->injectSettings(
                    $channelId,
                    $previewContext->getLanguageId(),
                    $this->locales->getLocaleForLanguageId($previewContext->getLanguageId()),
                    $previewContext->getContext(),
                );
            }
            try {
                $result = $this->contextRenderer->render($source, $previewContext, $includes, $namespace);
            } finally {
                $this->translator?->resetInjection();
            }
        } else {
            $locale = $this->locales?->getLocaleForLanguageId($this->languageId($request, $context));
            $result = $includes ? $this->renderer->toHtmlWithIncludes($source, $context, locale: $locale, namespace: $namespace)
                : new CarveIncludeResult($this->renderer->toHtml($source, locale: $locale, namespace: $namespace));
        }
        $resultData = $result->toArray();
        // Included files can supply references missing from the editor's source.
        $referenceChecks = !array_filter($result->dependencies, static fn (array $dependency): bool => $dependency['resolved']);
        $resultData['diagnostics'] = array_map(
            static fn ($warning): array => $warning->toArray(),
            $this->renderer->lint($source, $diagnosticChannelId, $locale, $referenceChecks, $result->html),
        );
        $resultData['commercePreview'] = is_string($channelId) && $channelId !== '';

        return new JsonResponse($resultData);
    }

    #[Route(path: '/api/_action/carve/import', name: 'api.action.carve.import', methods: ['POST'])]
    public function import(Request $request, Context $context): JsonResponse
    {
        $this->authorize($context);
        $source = $this->source($request);
        $format = $request->getPayload()->get('format');
        if (!in_array($format, ['html', 'markdown'], true)) {
            throw new BadRequestHttpException('Choose HTML or Markdown.');
        }
        $conversion = $format === 'html' ? (new HtmlToCarve())->convertWithFidelityReport($source)
            : (new MarkdownToCarve())->convertWithFidelityReport($source);

        return new JsonResponse(['source' => $conversion->value, 'report' => $conversion->report()]);
    }

    #[Route(path: '/api/_action/carve/includes', name: 'api.action.carve.includes', methods: ['GET'])]
    public function includes(Context $context): JsonResponse
    {
        if (!$context->isAllowed(CarveIncludeGate::PRIVILEGE)) {
            throw new MissingPrivilegeException([CarveIncludeGate::PRIVILEGE]);
        }
        $root = $this->gate?->root();
        $paths = [];
        if ($root !== null) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
            $iterator->setMaxDepth(8);
            $visited = 0;
            foreach ($iterator as $file) {
                if (++$visited > 10000) {
                    break;
                }
                if (!$file instanceof SplFileInfo || $file->isLink() || !$file->isFile() || $file->getExtension() !== 'crv') {
                    continue;
                }
                $path = $this->gate->containedIdentity($file->getRealPath() ?: null);
                if (is_string($path) && preg_match('~^[a-zA-Z0-9_./-]+\.crv$~', $path)) {
                    $paths[] = $path;
                }
                if (count($paths) >= 500) {
                    break;
                }
            }
        }
        sort($paths);

        return new JsonResponse(['paths' => $paths, 'limit' => 500]);
    }

    private function source(Request $request): string
    {
        if (strlen($request->getContent()) > 262144) {
            throw new HttpException(413, 'Carve preview accepts at most 256 KiB.');
        }
        $source = $request->getPayload()->get('source', '');
        if (!is_string($source)) {
            throw new BadRequestHttpException('Source must be a string.');
        }
        if (strlen($source) > 262144) {
            throw new HttpException(413, 'Carve preview accepts at most 256 KiB.');
        }

        return $source;
    }

    private function languageId(Request $request, Context $context): string
    {
        $languageId = $request->getPayload()->get('languageId', $context->getLanguageId());
        if (!is_string($languageId) || !Uuid::isValid($languageId)) {
            throw new BadRequestHttpException('Invalid language ID.');
        }

        return $languageId;
    }

    private function authorize(Context $context): void
    {
        foreach (['cms_page:read', 'product:read', 'category:read', 'product_manufacturer:read', CarveIncludeGate::PRIVILEGE] as $privilege) {
            if ($context->isAllowed($privilege)) {
                return;
            }
        }

        throw new MissingPrivilegeException(['cms_page:read']);
    }
}
