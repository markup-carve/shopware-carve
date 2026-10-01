<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

class CarveLiteral
{
    public static function escape(?string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value ?? '');

        return preg_replace('/([[:punct:]])/', '\\\\$1', $value) ?? '';
    }
}
