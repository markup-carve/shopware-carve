<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use RuntimeException;

/**
 * Thrown for an output target (`text`, `markdown`, `ansi`) that carve-php 0.2 does not register yet.
 */
class CarveWriterUnavailable extends RuntimeException
{
    public function __construct(public readonly string $target)
    {
        parent::__construct(sprintf('The Carve "%s" writer is not available yet in carve-php 0.2.', $target));
    }
}
