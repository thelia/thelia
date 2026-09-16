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

namespace Thelia\Mailer;

/**
 * Rewrites the plain Smarty variables of a mail message into their Twig form.
 *
 * Thelia 3 renders the subject and the body of a message with Twig, but a module ported from
 * Thelia 2 often kept the Smarty wording of the messages its setup.sql seeds: the shop then
 * mails `Payment of order {$order_ref}` verbatim to the customer. Only a bare `{$name}` is
 * rewritten — a modifier, a property access or a Smarty tag is left untouched rather than
 * half-translated, and a message already written in Twig is never touched at all.
 */
final class LegacyMessagePlaceholders
{
    private const SMARTY_VARIABLE = '/\{\$([A-Za-z_][A-Za-z0-9_]*)\}/';

    public static function interpolate(?string $source): string
    {
        $source = (string) $source;

        if (str_contains($source, '{{')) {
            return $source;
        }

        return preg_replace(self::SMARTY_VARIABLE, '{{ $1 }}', $source) ?? $source;
    }
}
