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

namespace Thelia\Module;

use Symfony\Component\Finder\Finder;
use Thelia\Module\Exception\InvalidXmlDocumentException;

/**
 * Class ModuleDescriptorValidator.
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class ModuleDescriptorValidator
{
    protected static $versions = [
        '1' => 'module.xsd',
        '2' => 'module-2_1.xsd',
        '3' => 'module-2_2.xsd',
    ];
    protected Finder $xsdFinder;
    protected $moduleVersion;

    public function __construct()
    {
        $this->xsdFinder = new Finder();
        $this->xsdFinder
            ->name('*.xsd')
            ->in(__DIR__.'/schema/module/');
    }

    public function getModuleVersion()
    {
        return $this->moduleVersion;
    }

    public function validate($xml_file, $version = null): bool
    {
        $this->moduleVersion = null;
        $dom = new \DOMDocument();
        $errors = [];
        $notXml = $this->load($dom, (string) $xml_file);

        if (null === $notXml) {
            /** @var \SplFileInfo $xsdFile */
            foreach ($this->xsdFinder as $xsdFile) {
                $xsdVersion = array_search($xsdFile->getBasename(), self::$versions, true);

                // The keys of the table are read back as integers: a version is compared as text.
                if (false === $xsdVersion || (null !== $version && (string) $version !== (string) $xsdVersion)) {
                    continue;
                }

                $errors = $this->schemaValidate($dom, $xsdFile);

                if ([] === $errors) {
                    $this->moduleVersion = $xsdVersion;

                    return true;
                }
            }
        }

        $reason = match (true) {
            null !== $notXml => 'it is not well-formed XML ('.implode(', ', $notXml).')',
            [] === $errors => \sprintf('no descriptor schema matches version %s', null === $version ? 'any' : (string) $version),
            default => implode(', ', $errors),
        };

        // Shown to the administrator who uploads the module: the module it is about, never
        // where the server unpacked it.
        throw new InvalidXmlDocumentException(\sprintf('The %s is not a valid file: %s', self::describe((string) $xml_file), $reason));
    }

    /**
     * Loads the descriptor into $dom: null once loaded, otherwise what libxml has to say
     * about a file that is not XML, without a path of the server, and never a warning of
     * PHP. No network access for an entity or a DTD a descriptor would point at.
     *
     * @return list<string>|null
     */
    private function load(\DOMDocument $dom, string $xmlFile): ?array
    {
        if (!is_file($xmlFile)) {
            return ['it is not a file'];
        }

        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            libxml_clear_errors();

            if ($dom->load($xmlFile, \LIBXML_NONET)) {
                return null;
            }

            return array_map(
                static fn (\LibXMLError $error): string => self::withoutPath(trim($error->message), $xmlFile),
                libxml_get_errors(),
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }
    }

    /**
     * The descriptor as the administrator knows it: "module.xml of <module>" for one at its
     * place in a module (<module>/Config/module.xml), the file name alone anywhere else.
     */
    private static function describe(string $xmlFile): string
    {
        $file = basename($xmlFile);
        $configFolder = \dirname($xmlFile);

        if ('Config' !== basename($configFolder) || \in_array(basename(\dirname($configFolder)), ['', '.', '..'], true)) {
            return $file;
        }

        return \sprintf('%s of %s', $file, basename(\dirname($configFolder)));
    }

    /**
     * A message of libxml about the file, without a path of the server. libxml quotes the
     * path of a file it could not open, in full, resolved and normalised: whatever it
     * quoted there becomes the name of the file. Then the path as given and as resolved,
     * wherever they stand, and last any other path quoted.
     */
    private static function withoutPath(string $message, string $xmlFile): string
    {
        $file = basename($xmlFile);

        if ('' === $file) {
            return $message;
        }

        $message = (string) preg_replace('#(failed to load external entity )".*"#s', '$1"'.$file.'"', $message);
        $message = str_replace(self::formsOf($xmlFile), $file, $message);

        return (string) preg_replace('#"[^"]*/([^"/]+)"#', '"$1"', $message);
    }

    /**
     * The path as given and as the file system resolves it, the longest first so that
     * the shorter one never eats a part of the longer.
     *
     * @return list<string>
     */
    private static function formsOf(string $path): array
    {
        $resolvedFolder = realpath(\dirname($path));
        $forms = array_values(array_unique(array_filter([
            $path,
            false === $resolvedFolder ? null : $resolvedFolder.\DIRECTORY_SEPARATOR.basename($path),
        ])));
        usort($forms, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return $forms;
    }

    /**
     * Validate the schema of a XML file with a given xsd file.
     *
     * @param \DOMDocument $dom     The XML document
     * @param \SplFileInfo $xsdFile The XSD file
     *
     * @return array an array of errors if validation fails, otherwise an empty array
     */
    protected function schemaValidate(\DOMDocument $dom, \SplFileInfo $xsdFile): array
    {
        $errorMessages = [];
        $previousErrorHandling = libxml_use_internal_errors(true);
        // What a message of libxml may quote of the server: the schema, and the descriptor.
        $paths = [...self::formsOf((string) $xsdFile->getRealPath()), ...self::formsOf((string) $dom->documentURI)];
        // A schema that cannot be read is a warning of PHP: an exception here, whatever the
        // environment does with a warning.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            if (!$dom->schemaValidate((string) $xsdFile->getRealPath())) {
                foreach (libxml_get_errors() as $error) {
                    $errorMessages[] = \sprintf(
                        'XML error "%s" [%d] (Code %d) on line %d column %d'."\n",
                        str_replace($paths, '', $error->message),
                        $error->level,
                        $error->code,
                        $error->line,
                        $error->column,
                    );
                }

                // A schema that said no without a word checked nothing for sure.
                if ([] === $errorMessages) {
                    $errorMessages[] = \sprintf('the descriptor could not be checked against %s', $xsdFile->getBasename());
                }
            }
        } catch (\ErrorException $notChecked) {
            // A schema that could not be read checks nothing: a descriptor it could not check
            // is not a valid one. What libxml has to say about the schema is the reason.
            $said = array_map(static fn (\LibXMLError $error): string => str_replace($paths, '', trim($error->message)), libxml_get_errors());
            $errorMessages[] = \sprintf('the descriptor could not be checked against %s (%s)', $xsdFile->getBasename(), implode(', ', [...$said, str_replace($paths, '', $notChecked->getMessage())]));
        } finally {
            restore_error_handler();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }

        return $errorMessages;
    }

    public function getDescriptor($xml_file): \SimpleXMLElement|false
    {
        $this->validate($xml_file);

        return @simplexml_load_file($xml_file, \SimpleXMLElement::class, \LIBXML_NONET);
    }
}
