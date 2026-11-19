<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use DateTimeImmutable;
use DateTimeZone;

class CarvePresentation
{
    public function register(CarveSetup $setup): void
    {
        $inline = [
            'rating-stars' => static function (string $value): string {
                $rating = self::boundedNumber($value, 5);

                return $rating === null ? self::escape($value) : self::ratingStars($rating);
            },
            'progress' => static function (string $value): string {
                $progress = self::boundedNumber($value, 100);

                return $progress === null ? self::escape($value) : self::progress($progress);
            },
            'color' => self::color(...),
            'unit' => self::unit(...),
            'coupon' => self::coupon(...),
            'countdown' => self::countdown(...),
            'video' => self::video(...),
        ];
        foreach ($inline as $name => $render) {
            $setup->on('inline', $name, static fn (CarveHookCall $call): string => $render($call->argument()));
        }
        $setup->on('inline', 'badge', static function (CarveHookCall $call): string {
            [$label, $inlineVariant] = array_pad(explode('|', $call->argument(), 2), 2, '');
            $variant = $call->attribute('variant') ?: $inlineVariant;
            if (!in_array($variant, ['info', 'success', 'warning', 'danger'], true)) {
                $variant = 'info';
            }

            return '<span class="carve-badge carve-badge--' . $variant . '">' . self::escape(trim($label)) . '</span>';
        });
        foreach (['faq' => 'Question', 'accordion' => 'Details'] as $kind => $default) {
            $setup->on('div', $kind, static function (CarveHookCall $call) use ($kind, $default): string {
                $summary = $call->title();

                return '<details class="carve-' . $kind . '"><summary>' . self::escape($summary !== '' ? $summary : $default)
                    . '</summary>' . $call->childrenHtml() . '</details>';
            });
        }
    }

    private static function boundedNumber(string $value, int $maximum): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float)$value;

        return is_finite($number) && $number >= 0 && $number <= $maximum ? round($number, 1) : null;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    private static function ratingStars(float $rating): string
    {
        $label = self::number($rating);
        $width = self::number($rating * 20);

        return '<span class="carve-rating-stars" role="img" aria-label="' . $label . ' out of 5 stars">'
            . '<span aria-hidden="true">★★★★★</span>'
            . '<span class="carve-rating-stars__fill" aria-hidden="true" style="width: ' . $width . '%">★★★★★</span>'
            . '</span>';
    }

    private static function progress(float $progress): string
    {
        $value = self::number($progress);

        return '<progress class="carve-progress" value="' . $value . '" max="100">' . $value . '%</progress>';
    }

    private static function color(string $value): string
    {
        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/D', $value) !== 1) {
            return self::escape($value);
        }

        return '<span class="carve-color"><span class="carve-color__swatch" style="background-color: ' . $value
            . '" aria-hidden="true"></span><code>' . self::escape($value) . '</code></span>';
    }

    private static function unit(string $value): string
    {
        if (preg_match('/^([+-]?\d+(?:[.,]\d+)?)\s+(.+)$/uD', $value, $parts) !== 1) {
            return self::escape($value);
        }

        return '<span class="carve-unit">' . self::escape($parts[1]) . '&#8239;' . self::escape($parts[2]) . '</span>';
    }

    private static function coupon(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $value) !== 1) {
            return self::escape($value);
        }
        $code = self::escape($value);

        return '<span class="carve-coupon"><code>' . $code . '</code><button type="button" class="carve-coupon__copy"'
            . ' data-code="' . $code . '" aria-label="Copy coupon ' . $code . '">Copy</button></span>';
    }

    private static function countdown(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return self::escape($value);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return self::escape($value);
        }

        return '<time class="carve-countdown" datetime="' . $value . '" data-target="' . $value . '">' . $value . '</time>';
    }

    private static function video(string $value): string
    {
        if (preg_match('/^youtube:([A-Za-z0-9_-]{11})$/D', $value, $matches) !== 1) {
            return self::escape($value);
        }

        return '<span class="carve-video"><button type="button" class="carve-video__load" data-video-id="'
            . $matches[1] . '" aria-label="Load YouTube video">Load video</button></span>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
