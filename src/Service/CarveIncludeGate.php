<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Decides whether a render context may resolve files, and holds the one
 * resolver that is allowed to do it.
 *
 * Two conditions have to hold together. An administrator configures an absolute
 * containment root, and the acting identity carries the dedicated privilege.
 * General CMS editing rights are not that privilege: a merchant who may write a
 * product description is not a server administrator.
 *
 * A non-admin source is allowed everything by Shopware's own
 * `Context::isAllowed()`, so the storefront render of content that was already
 * authored under the gate expands as the administration showed it would.
 */
class CarveIncludeGate
{
    /**
     * @var string
     */
    public const PRIVILEGE = 'carve.include_expand';

    /**
     * @var string
     */
    public const CONFIG_KEY = 'ShopwareCarve.config.includeRoot';

    private bool $built = false;

    private ?FilesystemIncludeResolver $resolver = null;

    private ?string $root = null;

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    public function isGranted(Context $context): bool
    {
        return $context->isAllowed(self::PRIVILEGE);
    }

    /**
     * The configured resolver, or null while inclusion is disabled.
     */
    public function resolver(): ?FilesystemIncludeResolver
    {
        $this->build();

        return $this->resolver;
    }

    /**
     * The canonical containment root, or null while inclusion is disabled.
     */
    public function root(): ?string
    {
        $this->build();

        return $this->root;
    }

    /**
     * The identity as a path relative to the root, or a fixed marker when it
     * names anything else. A resolver message may embed an absolute path, and a
     * denial keeps the directive's own spelling, so neither is reported.
     */
    public function containedIdentity(?string $identity): ?string
    {
        $root = $this->root();
        if ($identity === null || $root === null) {
            return $identity;
        }
        if ($identity === $root) {
            return '.';
        }
        if (str_starts_with($identity, $root . DIRECTORY_SEPARATOR)) {
            $identity = substr($identity, strlen($root) + 1);
        } elseif (str_starts_with($identity, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $identity) === 1) {
            return CarveIncludeResult::OUTSIDE_ROOT;
        }
        $identity = str_replace('\\', '/', $identity);

        return in_array('..', explode('/', $identity), true) ? CarveIncludeResult::OUTSIDE_ROOT : $identity;
    }

    /**
     * @throws \RuntimeException
     */
    private function build(): void
    {
        if ($this->built) {
            return;
        }
        $this->built = true;

        $configured = $this->systemConfig->get(self::CONFIG_KEY);
        if (!is_string($configured) || trim($configured) === '') {
            return;
        }

        // The resolver owns the rule that a configured root must be absolute, so
        // a value it refuses never reaches a render. Naming the setting here is
        // the only thing added.
        try {
            $this->resolver = new FilesystemIncludeResolver($configured);
        } catch (RuntimeException $exception) {
            throw new RuntimeException(self::CONFIG_KEY . ': ' . $exception->getMessage(), 0, $exception);
        }
        $this->root = rtrim((string)realpath($configured), DIRECTORY_SEPARATOR);
    }
}
