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

use Thelia\Tools\TerminalText;

/**
 * Reads an XML file a module or a template ships (module.xml, template.xml, a Propel
 * schema.xml) and checks it against a schema, closed: what libxml has to say is the
 * reason given, never a warning of PHP, and a file that could not be read or checked is
 * never passed. The file and the schema are read by PHP and handed to libxml as bytes,
 * never as a path: a path is a URI to libxml, which decodes it. Bytes have no base: a
 * relative reference (an include of a schema, an entity of a descriptor) would resolve
 * against the working directory, so none is loaded (no network, no DTD, no entity), a
 * file that declares a document type is refused, and no schema of the core includes
 * another. A reason names a file, never where it is on the server.
 */
final class XmlDescriptor
{
    private function __construct()
    {
    }

    /**
     * Loads the descriptor into $dom: nothing once loaded, otherwise why it could not be.
     * Once loaded, the document knows its file (documentURI, baseURI), as one loaded by
     * path did: a reader that tells documents apart by it still can. A document type is
     * refused: an entity it declares is not resolved, and would be copied as it is into
     * whatever is written from the document (the combined Propel schema), which then
     * could not be read.
     *
     * @return list<string>
     */
    public static function loadingErrors(\DOMDocument $dom, string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return ['it is not a readable file'];
        }

        if (0 === filesize($file)) {
            return ['it is empty'];
        }

        $notLoaded = self::askingLibxml(
            static function () use ($dom, $file): bool {
                $xml = file_get_contents($file);

                if (false === $xml || !$dom->loadXML($xml, \LIBXML_NONET)) {
                    return false;
                }

                $dom->documentURI = $file;

                return true;
            },
            'it could not be loaded',
            $file,
        );

        if ([] === $notLoaded && null !== $dom->doctype) {
            return ['it declares a document type, which none may'];
        }

        return $notLoaded;
    }

    /**
     * The descriptor as SimpleXML, for a caller that has had it validated or takes false
     * for an answer: false when it cannot be read or is not well-formed XML, without a
     * warning of PHP, and nothing left in the error buffer of libxml.
     */
    public static function read(string $file): \SimpleXMLElement|false
    {
        if (!is_file($file) || !is_readable($file)) {
            return false;
        }

        $previousErrorHandling = libxml_use_internal_errors(true);
        set_error_handler(static fn (): bool => true);

        try {
            $xml = file_get_contents($file);

            return false === $xml || '' === $xml ? false : simplexml_load_string($xml, \SimpleXMLElement::class, \LIBXML_NONET);
        } finally {
            restore_error_handler();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }
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
            static function () use ($dom, $schemaFile): bool {
                $schema = file_get_contents($schemaFile);

                return false !== $schema && $dom->schemaValidateSource($schema);
            },
            'the descriptor could not be checked against '.self::printable(basename($schemaFile)),
            $schemaFile,
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
     * The text fit to print in a reason, on one line: a value of the descriptor or a name
     * of a file may carry a control character, a mark that reorders or hides what follows,
     * a line break, or a byte that is not UTF-8, that a log, a terminal or a page would
     * obey: the characters TerminalText replaces.
     */
    public static function printable(string $text): string
    {
        return TerminalText::onOneLine($text);
    }

    /**
     * Runs $ask with the errors of libxml kept for us, and any warning of PHP turned into
     * an exception (whatever the environment does with a warning, and whether the call
     * was silenced with @): what either has to say is the reason. The error mode of
     * libxml and the error handler are given back as they were; the error buffer of
     * libxml is emptied, before and after (libxml lets no error be put back). libxml
     * gets bytes and quotes no path; PHP, reading $file, would quote it whole (the file
     * gone between the check and the read, the schema missing): the reasons name it by
     * its name alone.
     *
     * @param \Closure(): bool $ask
     *
     * @return list<string> empty when $ask answered yes
     */
    private static function askingLibxml(\Closure $ask, string $refusal, string $file): array
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

            return [] === $said ? [self::printable($refusal)] : $said;
        } catch (\ErrorException|\ValueError|\TypeError $notAnswered) {
            // The refusal, then what libxml and PHP had to say, each on its own.
            $saidByPhp = str_replace($file, basename($file), trim($notAnswered->getMessage()));

            return array_values(array_unique(array_map(self::printable(...), [$refusal, ...self::saidByLibxml(), $saidByPhp])));
        } finally {
            restore_error_handler();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }
    }

    /**
     * What libxml said, each error by its message, its code and its line, fit to print.
     *
     * @return list<string>
     */
    private static function saidByLibxml(): array
    {
        return array_values(array_filter(array_map(
            static fn (\LibXMLError $error): string => '' === trim($error->message) ? '' : self::printable(\sprintf('%s (Code %d) on line %d', trim($error->message), $error->code, $error->line)),
            libxml_get_errors(),
        )));
    }
}
