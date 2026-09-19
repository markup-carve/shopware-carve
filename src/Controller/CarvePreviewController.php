<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Controller;

use MarkupCarve\Shopware\Service\CarveRenderer;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Server-side preview for the Carve CMS element.
 *
 * The browser preview renders with carve-js, which has no filesystem, so it
 * cannot show what an include will produce. This route runs the persisted CMS
 * render path instead, under the administration user's own Context, so the gate
 * and the resolver configuration are the ones the storefront will use.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class CarvePreviewController
{
    public function __construct(private readonly CarveRenderer $renderer)
    {
    }

    #[Route(path: '/api/_action/carve/preview', name: 'api.action.carve.preview', methods: ['POST'])]
    public function preview(Request $request, Context $context): JsonResponse
    {
        $source = $request->request->get('source');

        return new JsonResponse(
            $this->renderer->toHtmlWithIncludes(is_string($source) ? $source : null, $context)->toArray(),
        );
    }
}
