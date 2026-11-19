<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use Closure;
use MarkupCarve\Carve\Attrs;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\DocumentTransform;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Node;
use MarkupCarve\Carve\RenderResult;

/**
 * One render configuration for carve-php 0.2: the option map, the host render hooks and document transforms.
 *
 * Takes the place of the 0.1 `CarveConverter`. A hook claims `:name[...]` (inline), `::: name` (div) or a
 * fence language; hooks on the same name run in registration order and the first non-null HTML wins, a
 * null from every hook keeps the core rendering.
 */
class CarveSetup
{
    /**
     * @var string
     */
    public const NAMESPACE = 'shopware';

    /**
     * @var array<string, array<string, array<\Closure(\MarkupCarve\Shopware\Service\CarveHookCall): ?string>>>
     */
    private array $hooks = ['inline' => [], 'div' => [], 'fence' => []];

    /**
     * @var array<\MarkupCarve\Carve\Extension\ExtensionDefinition>
     */
    private array $definitions = [];

    /**
     * @var array<\MarkupCarve\Carve\Extension\DocumentTransform>
     */
    private array $transforms = [];

    /**
     * @var array<\Closure(string): string>
     */
    private array $postProcessors = [];

    /**
     * @var array<\MarkupCarve\Carve\Extension\ExtensionDefinition>|null
     */
    private ?array $built = null;

    /**
     * @param array<string, mixed> $options carve-php 0.2 options other than `extensions`.
     */
    public function __construct(private array $options = [])
    {
    }

    /**
     * @param string $form `inline`, `div` or `fence`.
     * @param string $name
     * @param \Closure(\MarkupCarve\Shopware\Service\CarveHookCall): ?string $hook
     */
    public function on(string $form, string $name, Closure $hook): void
    {
        $this->hooks[$form][$name][] = $hook;
        $this->built = null;
    }

    public function addExtension(ExtensionDefinition ...$definitions): void
    {
        array_push($this->definitions, ...$definitions);
        $this->built = null;
    }

    public function addTransform(DocumentTransform $transform): void
    {
        $this->transforms[] = $transform;
    }

    /**
     * @param \Closure(string): string $processor
     */
    public function addPostProcessor(Closure $processor): void
    {
        $this->postProcessors[] = $processor;
    }

    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function setOption(string $name, mixed $value): void
    {
        $this->options[$name] = $value;
    }

    /**
     * @return array<\MarkupCarve\Carve\Extension\ExtensionDefinition>
     */
    public function extensions(): array
    {
        return $this->built ??= [...$this->definitions, ...$this->hookDefinitions()];
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public function options(array $extra = []): array
    {
        return ['extensions' => $this->extensions()] + $extra + $this->options;
    }

    /**
     * Options for a call that only parses (parse, lint, format): render options are refused there.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public function parseOptions(array $extra = []): array
    {
        return ['extensions' => $this->extensions()] + $extra;
    }

    /**
     * @param string $source
     * @param array<string, mixed> $extra
     */
    public function parse(string $source, array $extra = []): Node
    {
        return Carve::parse($source, $this->parseOptions($extra));
    }

    /**
     * @param \MarkupCarve\Carve\Node|string $input
     * @param array<string, mixed> $extra
     */
    public function render(Node|string $input, array $extra = []): string
    {
        return $this->renderWithDiagnostics($input, $extra)->html;
    }

    /**
     * @param \MarkupCarve\Carve\Node|string $input
     * @param array<string, mixed> $extra
     */
    public function renderWithDiagnostics(Node|string $input, array $extra = []): RenderResult
    {
        $result = Carve::renderWithDocumentTransforms($input, $this->transforms, $this->options($extra));
        $html = $result->html;
        foreach ($this->postProcessors as $processor) {
            $html = $processor($html);
        }

        return new RenderResult($html, $result->diagnostics);
    }

    /**
     * @return array<\MarkupCarve\Carve\Extension\ExtensionDefinition>
     */
    private function hookDefinitions(): array
    {
        $definitions = [];
        foreach ($this->hooks as $form => $byName) {
            foreach ($byName as $name => $hooks) {
                $qualified = self::NAMESPACE . '.' . $form . '-' . $name;
                $definitions[] = new ExtensionDefinition(
                    $qualified,
                    '1',
                    [],
                    matcher: ['form' => $form, 'name' => $name],
                    transform: static function (Claim $claim) use ($qualified, $form): Node {
                        $node = new Node($form === 'inline' ? 'inline_extension' : 'block_extension', []);
                        $attrs = $claim->node->f['attrs'] ?? null;
                        // An inline fallback holds core inlines only, so the claimed node's attributes ride in props.
                        $node->f = [
                            'name' => $qualified,
                            'version' => '1',
                            'slots' => [],
                            'props' => [
                                'source' => $claim->source,
                                'id' => $attrs instanceof Attrs ? $attrs->id : null,
                                'classes' => $attrs instanceof Attrs ? $attrs->classes : [],
                                'pairs' => $attrs instanceof Attrs ? $attrs->pairs : [],
                            ],
                            'fallback' => $form === 'inline' ? $claim->copyNode()->children ?? [] : [$claim->copyNode()],
                        ];

                        return $node;
                    },
                    renderer: static function (Node $node, RenderHost $host) use ($form, $name, $hooks): string {
                        $call = new CarveHookCall($form, $name, self::claimed($node) ?? $node, (array)($node->f['props']['source'] ?? []), $host);
                        foreach ($hooks as $hook) {
                            $html = $hook($call);
                            if ($html !== null) {
                                return $html;
                            }
                        }

                        return $call->fallbackHtml();
                    },
                );
            }
        }

        return $definitions;
    }

    /**
     * The core node a hook claimed, rebuilt from its extension node; null for any other node.
     */
    public static function claimed(Node $node): ?Node
    {
        $name = $node->f['name'] ?? null;
        $source = $node->f['props']['source'] ?? null;
        if (
            !in_array($node->type, ['inline_extension', 'block_extension'], true) || !is_string($name)
            || !str_starts_with($name, self::NAMESPACE . '.') || !is_array($source)
        ) {
            return null;
        }
        $fallback = $node->f['fallback'] ?? [];
        if ($node->type === 'block_extension') {
            return ($fallback[0] ?? null) instanceof Node ? $fallback[0] : null;
        }
        $props = $node->f['props'];
        $attrs = new Attrs();
        $attrs->id = $props['id'] ?? null;
        $attrs->classes = $props['classes'] ?? [];
        $attrs->pairs = $props['pairs'] ?? [];
        $claimed = new Node('inline_extension', is_array($fallback) ? $fallback : []);
        $claimed->f = ['name' => (string)($source['name'] ?? '')] + ($attrs->isEmpty() ? [] : ['attrs' => $attrs]);

        return $claimed;
    }
}
