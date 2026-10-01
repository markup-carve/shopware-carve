<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\Carve\Node\Inline\InlineExtension;
use MarkupCarve\Carve\Node\Node;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CarveResources
{
    /**
     * @param \Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository<\Shopware\Core\Content\Category\CategoryCollection> $categories
     * @param \Shopware\Core\System\SystemConfig\SystemConfigService $config
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $cacheTags
     * @param \Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface $seo
     * @param \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\Content\Media\MediaCollection> $mediaRepository
     * @param \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection> $manufacturers
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $manufacturers,
        private readonly SalesChannelRepository $categories,
        private readonly SeoUrlPlaceholderHandlerInterface $seo,
        private readonly SystemConfigService $config,
        private readonly EventDispatcherInterface $cacheTags,
    ) {
    }

    public function register(CarveConverter $converter, SalesChannelContext $context): void
    {
        $converter->on('render.inline_extension', function (RenderEvent $event) use ($context): void {
            $node = $event->getNode();
            if (!$node instanceof InlineExtension || !in_array($node->getExtensionType(), ['media', 'category', 'manufacturer', 'legal'], true)) {
                return;
            }
            $this->cacheTags->dispatch(new AddCacheTagEvent('carve-resources'));
            $type = $node->getExtensionType();
            $argument = $this->argument($node);
            $html = $this->escape($argument);
            if ($type === 'media' && Uuid::isValid($argument)) {
                $criteria = new Criteria([$argument]);
                $criteria->addAssociation('thumbnails');
                $media = $this->mediaRepository->search($criteria, $context->getContext())->first();
                if ($media !== null && !$media->isPrivate() && str_starts_with($media->getMimeType() ?? '', 'image/')) {
                    $html = $this->image($media, $node->getAttribute('alt'));
                }
            }
            if ($type === 'manufacturer' && Uuid::isValid($argument)) {
                $manufacturer = $this->manufacturers->search(new Criteria([$argument]), $context->getContext())->first();
                if ($manufacturer !== null) {
                    // Core has no manufacturer detail route. Link to storefront search by brand name.
                    $name = (string)($manufacturer->getTranslation('name') ?? $manufacturer->getName());
                    $html = $this->link($this->seo->generate('frontend.search.page', ['search' => $name]), $name);
                }
            }
            $categoryId = $argument;
            if ($type === 'legal') {
                $setting = match ($argument) {
                    'privacy' => 'privacyPage', 'tos' => 'tosPage', 'imprint' => 'imprintPage',
                    'withdrawal' => 'revocationPage', default => null,
                };
                $categoryId = $setting === null ? null : $this->config->get('core.basicInformation.' . $setting, $context->getSalesChannelId());
            }
            if (in_array($type, ['category', 'legal'], true) && is_string($categoryId) && Uuid::isValid($categoryId)) {
                $criteria = new Criteria([$categoryId]);
                $criteria->addFilter(new EqualsFilter('active', true));
                $criteria->addFilter(new EqualsFilter('type', CategoryDefinition::TYPE_PAGE));
                if ($type === 'category') {
                    $roots = array_filter([
                        $context->getSalesChannel()->getNavigationCategoryId(),
                        $context->getSalesChannel()->getFooterCategoryId(), $context->getSalesChannel()->getServiceCategoryId(),
                    ]);
                    $allowed = [];
                    foreach ($roots as $root) {
                        $allowed[] = new EqualsFilter('id', $root);
                        $allowed[] = new ContainsFilter('path', '|' . $root . '|');
                    }
                    if ($allowed === []) {
                        $event->setHtml($html);

                        return;
                    }
                    $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $allowed));
                }
                $category = $this->categories->search($criteria, $context)->first();
                if ($category !== null) {
                    $html = $this->link(
                        $this->seo->generate('frontend.navigation.page', ['navigationId' => $categoryId]),
                        (string)($category->getTranslation('name') ?? $category->getName() ?? $argument),
                    );
                }
            }
            $event->setHtml($html);
        });
    }

    private function image(MediaEntity $media, ?string $alt): string
    {
        $url = $media->getUrl();
        if (!$this->safeUrl($url)) {
            return '';
        }
        $sources = [];
        foreach ($media->getThumbnails() ?? [] as $thumbnail) {
            if ($thumbnail->getWidth() > 0 && $this->safeUrl($thumbnail->getUrl()) && !str_contains($thumbnail->getUrl(), ',')) {
                $sources[] = $this->escape($thumbnail->getUrl()) . ' ' . $thumbnail->getWidth() . 'w';
            }
        }
        $attributes = $sources === [] ? '' : ' srcset="' . implode(', ', $sources) . '" sizes="(max-width: 768px) 100vw, 50vw"';

        return '<img class="carve-media" src="' . $this->escape($url) . '" alt="'
            . $this->escape($alt ?? (string)($media->getTranslation('alt') ?? $media->getAlt() ?? ''))
            . '" loading="lazy" decoding="async"' . $attributes . '>';
    }

    private function safeUrl(string $url): bool
    {
        return preg_match('~^(?:https?://|/(?!/))[^\s]+$~i', $url) === 1;
    }

    private function argument(Node $node): string
    {
        $document = new Document();
        foreach ($node->getChildren() as $child) {
            $document->appendChild(clone $child);
        }

        return trim((new PlainTextRenderer())->render($document));
    }

    private function link(string $url, string $name): string
    {
        return '<a href="' . $this->escape($url) . '">' . $this->escape($name) . '</a>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
