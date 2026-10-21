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

namespace Thelia\Domain\CMS\Publication\Exception;

use Thelia\Core\Translation\Translator;

/**
 * A publication gesture the row cannot take. The message is meant for the person who
 * made the gesture and is translated by the Thelia translator (domain `core`), so a
 * back office, the API and the command show it as it is.
 */
class PublicationException extends \DomainException
{
    /**
     * @param array<string, string> $parameters
     */
    protected static function translated(string $message, array $parameters = []): string
    {
        try {
            return Translator::getInstance()->trans($message, $parameters);
        } catch (\RuntimeException) {
            // No translator yet: a bare test, or a command run before the kernel wired it.
            return strtr($message, $parameters);
        }
    }
}
