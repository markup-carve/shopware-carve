<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarveIncludeResult;
use Shopware\Core\Framework\Context;

class CarveIncludeRenderTest extends CarveIncludeTestCase
{
    public function testAContainedIncludeExpandsAndIsReported(): void
    {
        $root = $this->makeRoot([
            'chapters/one.crv' => "Included.\n",
        ]);

        $result = $this->makeRenderer($root)->toHtmlWithIncludes("{{ chapters/one.crv }}\n", $this->privilegedAdmin());

        self::assertTrue($result->expanded);
        self::assertStringContainsString('Included.', $result->html);
        self::assertSame([['path' => 'chapters/one.crv', 'resolved' => true]], $result->dependencies);
        self::assertSame([], $result->warnings);
    }

    public function testTraversalAndSymlinkEscapeStayLiteralAndReportNoPath(): void
    {
        $root = $this->makeRoot();
        $outside = $this->makeOutsideFile("SECRET\n");
        symlink($outside, $root . '/linked.crv');
        $source = '{{ ../' . basename($outside) . " }}\n\n{{ linked.crv }}\n\n{{ " . $outside . " }}\n";

        $result = $this->makeRenderer($root)->toHtmlWithIncludes($source, $this->privilegedAdmin());

        self::assertStringNotContainsString('SECRET', $result->html);
        // The traversal and the absolute spec name paths outside the root and
        // are masked; the symlink names one inside it, so its own spelling says
        // which directive was refused.
        self::assertSame(
            [
                ['path' => CarveIncludeResult::OUTSIDE_ROOT, 'resolved' => false],
                ['path' => 'linked.crv', 'resolved' => false],
                ['path' => CarveIncludeResult::OUTSIDE_ROOT, 'resolved' => false],
            ],
            $result->dependencies,
        );
        self::assertCount(3, $result->warnings);
        foreach ($result->warnings as $warning) {
            self::assertSame('include-unresolved', $warning['rule']);
            self::assertSame(['rule', 'message', 'file', 'line', 'column'], array_keys($warning));
        }
        // `message` quotes the directive the author typed, which is theirs to
        // know. The containment root is the server's, and the resolver's own
        // message embeds it on the engine's `detail` channel, which this plugin
        // never reports.
        self::assertStringNotContainsString($root, serialize($result->toArray()));
    }

    public function testANestedRelativeIncludeResolvesAgainstItsOwnParent(): void
    {
        $root = $this->makeRoot([
            'chapters/one.crv' => "{{ parts/deep.crv }}\n",
            'chapters/parts/deep.crv' => "DEEP\n",
        ]);

        $result = $this->makeRenderer($root)->toHtmlWithIncludes("{{ chapters/one.crv }}\n", $this->privilegedAdmin());

        self::assertStringContainsString('DEEP', $result->html);
        self::assertSame(
            [
                ['path' => 'chapters/one.crv', 'resolved' => true],
                ['path' => 'chapters/parts/deep.crv', 'resolved' => true],
            ],
            $result->dependencies,
        );
    }

    public function testCmsEditingRightsAloneLeaveDirectivesLiteral(): void
    {
        $root = $this->makeRoot(['secret.crv' => "SECRET\n"]);

        $result = $this->makeRenderer($root)->toHtmlWithIncludes("{{ secret.crv }}\n", $this->cmsOnlyAdmin());

        self::assertFalse($result->expanded);
        self::assertStringNotContainsString('SECRET', $result->html);
        self::assertStringContainsString('{{ secret.crv }}', $result->html);
        self::assertSame([], $result->dependencies);
    }

    public function testAnUnsetRootLeavesDirectivesLiteralForEveryIdentity(): void
    {
        $root = $this->makeRoot(['secret.crv' => "SECRET\n"]);
        $renderer = $this->makeRenderer(null);

        foreach ([$this->privilegedAdmin(), $this->cmsOnlyAdmin(), Context::createDefaultContext()] as $context) {
            $result = $renderer->toHtmlWithIncludes("{{ secret.crv }}\n", $context);
            self::assertFalse($result->expanded);
            self::assertStringNotContainsString('SECRET', $result->html);
        }
        self::assertDirectoryExists($root);
    }

    public function testUntrustedEntityFieldsKeepDirectivesLiteral(): void
    {
        $root = $this->makeRoot(['secret.crv' => "SECRET\n"]);
        $renderer = $this->makeRenderer($root);

        // Product, category and manufacturer bodies and storefront reviews all
        // render through these, whoever wrote them, and an import writes those
        // fields too.
        foreach (['toHtml', 'toHtmlUgc', 'toText', 'toMarkdown'] as $method) {
            $rendered = $renderer->{$method}("{{ secret.crv }}\n");
            self::assertStringNotContainsString('SECRET', $rendered, $method);
            self::assertStringContainsString('{{ secret.crv }}', $rendered, $method);
        }
    }

    public function testAMissingTargetIsReportedSoACacheCanInvalidateOnItsArrival(): void
    {
        $root = $this->makeRoot();
        $renderer = $this->makeRenderer($root);

        $before = $renderer->toHtmlWithIncludes("{{ later.crv }}\n", $this->privilegedAdmin());
        self::assertStringContainsString('{{ later.crv }}', $before->html);
        // Named where the file WOULD be, which is the path a cache watches:
        // creating it is exactly what makes the directive start working.
        self::assertSame([['path' => 'later.crv', 'resolved' => false]], $before->dependencies);

        file_put_contents($root . '/later.crv', "ARRIVED\n");
        $after = $renderer->toHtmlWithIncludes("{{ later.crv }}\n", $this->privilegedAdmin());
        self::assertStringContainsString('ARRIVED', $after->html);
        self::assertSame([['path' => 'later.crv', 'resolved' => true]], $after->dependencies);
    }
}
