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

namespace Thelia\Domain\Ai\Tool\Exception;

use Thelia\Domain\Ai\Exception\AiExceptionInterface;

/**
 * Why a token of the AI tools was not created or revoked. The message never carries a token.
 */
final class AiToolTokenException extends \RuntimeException implements AiExceptionInterface
{
    public static function forbidden(string $right): self
    {
        return new self(\sprintf('The administrator does not hold the right "%s".', $right));
    }

    public static function invalid(string $reason): self
    {
        return new self($reason);
    }

    public static function notFound(): self
    {
        return new self('The token does not exist.');
    }
}
