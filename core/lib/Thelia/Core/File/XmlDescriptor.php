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

namespace Thelia\Core\File;

/**
 * Reads an XML descriptor (module.xml, template.xml) and checks it against a schema,
 * closed: what libxml has to say is the reason given, never a warning of PHP, and a
 * descriptor that could not be read or checked is never passed. No network access for
 * an entity or a DTD a descriptor would point at.
 */
final class XmlDescriptor
{
    private function __construct()
    {
    }

    /**
     * Loads the descriptor into $dom: nothing once loaded, otherwise why it could not be.
     *
     * @return list<string>
     */
    public static function loadingErrors(\DOMDocument $dom, string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return ['it is not a readable file'];
        }

        return self::askingLibxml(
            static fn (): bool => $dom->load($file, \LIBXML_NONET),
            'it could not be loaded',
        );
    }

    /**
     * Checks the loaded descriptor against the schema: nothing when it conforms,
     * otherwise why not. A schema that cannot be read, or that says no without a word,
     * checks nothing: the descriptor is then refused with that reason.
     *
     * @return list<string>
     */
    public static function schemaErrors(\DOMDocument $dom, string $schemaFile): array
    {
        if ('' === $schemaFile) {
            return ['the schema is missing'];
        }

        return self::askingLibxml(
            static fn (): bool => $dom->schemaValidate($schemaFile),
            'the descriptor could not be checked against '.basename($schemaFile),
        );
    }

    /**
     * Runs $ask with the errors of libxml kept for us, and any warning of PHP turned into
     * an exception, whatever the environment does with a warning: what either has to say
     * is the reason. The error mode and the buffer of libxml, and the error handler, are
     * given back as they were.
     *
     * @param \Closure(): bool $ask
     *
     * @return list<string> empty when $ask answered yes
     */
    private static function askingLibxml(\Closure $ask, string $refusal): array
    {
        $previousErrorHandling = libxml_use_internal_errors(true);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            libxml_clear_errors();

            if ($ask()) {
                return [];
            }

            $said = self::saidByLibxml();

            return [] === $said ? [$refusal] : $said;
        } catch (\ErrorException|\ValueError|\TypeError $notAnswered) {
            return [\sprintf('%s (%s)', $refusal, implode(', ', [...self::saidByLibxml(), trim($notAnswered->getMessage())]))];
        } finally {
            restore_error_handler();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }
    }

    /**
     * What libxml said, each error by its message, its code and its line.
     *
     * @return list<string>
     */
    private static function saidByLibxml(): array
    {
        return array_values(array_filter(array_map(
            static fn (\LibXMLError $error): string => '' === trim($error->message) ? '' : \sprintf('%s (Code %d) on line %d', trim($error->message), $error->code, $error->line),
            libxml_get_errors(),
        )));
    }
}
