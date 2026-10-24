<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

final readonly class CarveStoredSource
{
    public function __construct(
        public string $entity,
        public string $id,
        public string $languageId,
        public string $field,
        public string $source,
    ) {
    }

    public function label(): string
    {
        return $this->entity . ':' . $this->id . ':' . $this->languageId . ':' . $this->field;
    }
}
