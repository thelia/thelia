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
 * Rewrites the plain Smarty variables and configuration reads of a mail message into their Twig form.
 *
 * Thelia 3 renders the subject and the body of a message with Twig, but a module ported from
 * Thelia 2 often kept the Smarty wording of the messages its setup.sql seeds, and a shop
 * migrated from Thelia 2 keeps the subjects of its own messages: the shop then mails
 * `Payment of order {$order_ref}` or `Your order at {config key="store_name"}` verbatim to the
 * customer. Only a bare `{$name}` and a `{config key="..."}` naming a plain key are rewritten —
 * a modifier, a property access, any other Smarty tag or attribute, a localized variant of the
 * tag, is left untouched rather than half-translated, and a message already written in Twig is
 * never touched at all.
 */
final class LegacyMessagePlaceholders
{
    private const SMARTY_VARIABLE = '/\{\$([A-Za-z_][A-Za-z0-9_]*)\}/';

    /**
     * `{config key="store_name"}` or `{config key='store_name'}`, with the spacing Smarty accepts
     * around `=` and before the closing brace, but none after the opening one (Smarty reads
     * `{ config` as literal text). A double-quoted key that Smarty would interpolate (`$`,
     * backticks, braces) or a key holding an escape is not a plain key, and is left alone.
     */
    private const SMARTY_CONFIG_TAG = '/\{config\s+key\s*=\s*(?|"([^"$`{}\\\\]*)"|\'([^\'\\\\]*)\')\s*\}/';

    public static function interpolate(?string $source): string
    {
        $source = (string) $source;

        if (str_contains($source, '{{')) {
            return $source;
        }

        $source = preg_replace_callback(
            self::SMARTY_CONFIG_TAG,
            // The key becomes a single-quoted Twig string: a quote in it is escaped, so a key
            // can never close the string and spill into the expression.
            static fn (array $match): string => \sprintf("{{ config('%s') }}", addcslashes($match[1], "'\\")),
            $source,
        ) ?? $source;

        return preg_replace(self::SMARTY_VARIABLE, '{{ $1 }}', $source) ?? $source;
    }
}
