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

namespace Thelia\Domain\Ai\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The key stored for a connection cannot be read: it was encrypted with another application secret
 * (APP_SECRET changed, or the database came from another shop). The key is never sent to the provider
 * as it is; the exception says how to recover, and never carries the key.
 */
#[Exclude]
final class AiKeyDecryptionException extends \RuntimeException implements AiExceptionInterface
{
    public function __construct(public readonly string $connection)
    {
        parent::__construct(\sprintf(
            'The API key of the AI connection "%s" cannot be decrypted: it was saved with another application secret (APP_SECRET). '
            .'Enter the key again in the connection, or put the previous secret in the environment variable THELIA_AI_PREVIOUS_SECRET and run "bin/console thelia:ai:rotate-keys". '
            .'You can also name an environment variable that holds the key in the connection.',
            $connection,
        ));
    }
}
