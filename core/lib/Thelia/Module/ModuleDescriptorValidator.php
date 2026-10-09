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
        $dom = new \DOMDocument();
        $errors = [];

        // No network access for an entity or a DTD a descriptor would point at. What
        // libxml has to say about a file that is not XML is the reason given, never a
        // warning of PHP.
        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            libxml_clear_errors();
            $loaded = $dom->load($xml_file, \LIBXML_NONET);
            $notXml = array_map(
                static fn (\LibXMLError $error): string => self::withoutPath(trim($error->message), (string) $xml_file),
                libxml_get_errors(),
            );
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previousErrorHandling);
        }

        if ($loaded) {
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
            !$loaded => 'it is not well-formed XML ('.implode(', ', $notXml).')',
            [] === $errors => \sprintf('no descriptor schema matches version %s', null === $version ? 'any' : (string) $version),
            default => implode(', ', $errors),
        };

        // Shown to the administrator who uploads the module: the module it is about, never
        // where the server unpacked it.
        throw new InvalidXmlDocumentException(\sprintf('The %s is not a valid file: %s', self::describe((string) $xml_file), $reason));
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
     * A message of libxml without a path of the server: libxml quotes, in full and
     * resolved, the path of a file it could not open. The path of the descriptor, as given
     * and as resolved, becomes the name of the file; then so does any other path quoted.
     */
    private static function withoutPath(string $message, string $xmlFile): string
    {
        $file = basename($xmlFile);
        $resolvedFolder = realpath(\dirname($xmlFile));
        $forms = array_filter([$xmlFile, false === $resolvedFolder ? null : $resolvedFolder.\DIRECTORY_SEPARATOR.$file]);
        $message = str_replace($forms, $file, $message);

        return (string) preg_replace('#"[^"]*/([^"/]+)"#', '"$1"', $message);
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

        try {
            if (!$dom->schemaValidate($xsdFile->getRealPath())) {
                $errors = libxml_get_errors();

                foreach ($errors as $error) {
                    $errorMessages[] = \sprintf(
                        'XML error "%s" [%d] (Code %d) on line %d column %d'."\n",
                        self::withoutPath($error->message, $dom->documentURI ?? ''),
                        $error->level,
                        $error->code,
                        $error->line,
                        $error->column,
                    );
                }

                libxml_clear_errors();
            }
        } catch (\ErrorException $notChecked) {
            // A schema that could not be read checks nothing: a descriptor it could not check
            // is not a valid one.
            $errorMessages[] = \sprintf('the descriptor could not be checked against %s (%s)', $xsdFile->getBasename(), $notChecked->getMessage());
        } finally {
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
