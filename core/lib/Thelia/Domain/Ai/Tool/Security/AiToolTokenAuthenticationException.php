<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\Ai\Tool\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Why a request to the MCP endpoint is turned away. The message is fixed: it never says whether a token exists.
 *
 * @internal
 */
final class AiToolTokenAuthenticationException extends AuthenticationException
{
    private ?\DateTimeImmutable $retryAfter = null;

    private bool $disabled = false;

    public static function disabled(): self
    {
        $exception = new self('The MCP server is off.');
        $exception->disabled = true;

        return $exception;
    }

    public static function rejected(): self
    {
        return new self('Unauthorized.');
    }

    public static function rateLimited(\DateTimeImmutable $retryAfter): self
    {
        $exception = new self('Too many requests.');
        $exception->retryAfter = $retryAfter;

        return $exception;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function retryAfter(): ?\DateTimeImmutable
    {
        return $this->retryAfter;
    }

    public function getMessageKey(): string
    {
        return 'Unauthorized.';
    }
}
