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
            'the descriptor could not be checked against '.self::printable(basename($schemaFile)),
        );
    }

    /**
     * Checks the loaded descriptor against the schemas of the versions it may be written
     * in, the latest version first: the first schema that accepts it tells its version,
     * with no error. Otherwise the errors are those of the latest schema tried, or the
     * one reason that none was.
     *
     * @param iterable<\SplFileInfo>                             $schemas  the schema files
     * @param array<string, string>                              $versions the schema file name of each version
     * @param \Closure(\DOMDocument, \SplFileInfo): list<string> $check    the errors of the descriptor against a schema
     *
     * @return array{version: int|string|null, errors: list<string>}
     */
    public static function matchingSchemaVersion(\DOMDocument $dom, iterable $schemas, array $versions, ?string $version, \Closure $check): array
    {
        $known = [];

        foreach ($schemas as $schemaFile) {
            $schemaVersion = array_search($schemaFile->getBasename(), $versions, true);

            // The keys of the table are read back as integers: a version is compared as text.
            if (false !== $schemaVersion && (null === $version || $version === (string) $schemaVersion)) {
                $known[(string) $schemaVersion] = [$schemaVersion, $schemaFile];
            }
        }

        // The file system lists the schemas in no order: the latest version is tried first,
        // and its errors are the ones told.
        uksort($known, static fn (string $left, string $right): int => strnatcmp($right, $left));
        $errors = [];

        foreach ($known as [$schemaVersion, $schemaFile]) {
            $said = $check($dom, $schemaFile);

            if ([] === $said) {
                return ['version' => $schemaVersion, 'errors' => []];
            }

            $errors = [] === $errors ? $said : $errors;
        }

        return ['version' => null, 'errors' => [] === $errors ? [\sprintf('no descriptor schema matches version %s', $version ?? 'any')] : $errors];
    }

    /**
     * The text fit to print in a reason: a value of the descriptor or a name of a file
     * may carry a control character, or a mark that reverses the direction of what
     * follows, that a log or a page would obey.
     */
    public static function printable(string $text): string
    {
        return (string) preg_replace('/[\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', ' ', $text);
    }

    /**
     * Runs $ask with the errors of libxml kept for us, and any warning of PHP turned into
     * an exception (whatever the environment does with a warning, and whether the call
     * was silenced with @): what either has to say is the reason. The error mode of
     * libxml and the error handler are given back as they were; the error buffer of
     * libxml is emptied, before and after (libxml lets no error be put back).
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
            // The refusal, then what libxml and PHP had to say, each on its own.
            return array_values(array_unique([$refusal, ...self::saidByLibxml(), trim($notAnswered->getMessage())]));
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
