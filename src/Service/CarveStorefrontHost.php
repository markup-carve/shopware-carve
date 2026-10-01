<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CarveStorefrontHost
{
    public static function resolve(?Request $request, SalesChannelContext $context): string
    {
        $preferred = rtrim($request?->headers->get('sw-storefront-url', '') ?? '', '/');
        $host = '';
        foreach ($context->getSalesChannel()->getDomains() ?? [] as $domain) {
            if ($domain->getLanguageId() !== $context->getLanguageId()) {
                continue;
            }
            $url = rtrim($domain->getUrl(), '/');
            if ($host === '') {
                $host = $url;
            }
            if ($url === $preferred) {
                return $url;
            }
        }

        return $host;
    }
}
