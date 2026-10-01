<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarveConverterFactory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CarveConverterFactoryTest extends TestCase
{
    public function testChannelsHaveIndependentSettingsAndAutomaticLocales(): void
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static function (string $key, ?string $channel): mixed {
            return match ($key) {
                'ShopwareCarve.config.symbols' => $channel === 'de-shop' ? 'brand=Deutsch' : 'brand=English',
                'ShopwareCarve.config.smartQuotes' => true,
                'ShopwareCarve.config.smartQuotesLocale' => 'auto',
                'ShopwareCarve.config.externalLinksNofollow', 'ShopwareCarve.config.externalLinksNewTab' => false,
                default => null,
            };
        });
        $factory = new CarveConverterFactory($config);
        $de = $factory->create('de-shop', 'de-DE')->convert(':brand: "Hallo" [link](https://example.com)');
        $en = $factory->create('en-shop', 'en-GB')->convert(':brand: "Hello"');
        self::assertStringContainsString('Deutsch', $de);
        self::assertStringContainsString('„Hallo“', $de);
        self::assertStringContainsString('English', $en);
        self::assertStringContainsString('“Hello”', $en);
        self::assertStringNotContainsString('nofollow', $de);
        self::assertStringNotContainsString('target=', $de);
        self::assertNotSame($factory->signature('de-shop', 'de-DE'), $factory->signature('en-shop', 'en-GB'));
    }
}
