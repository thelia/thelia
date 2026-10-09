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
use Thelia\Core\File\XmlDescriptor;
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
        $notLoaded = XmlDescriptor::loadingErrors($dom, (string) $xml_file);

        if ([] === $notLoaded) {
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
            [] !== $notLoaded => implode(', ', array_map(static fn (string $said): string => self::withoutPath($said, (string) $xml_file), $notLoaded)),
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
     * A message of libxml about the file, without a path of the server. libxml quotes the
     * path of a file it could not open, in full, resolved and normalised: whatever it
     * quoted there becomes the name of the file (the whole of it, as the path may hold a
     * quote). Then the path as given and as resolved, wherever they stand, and last any
     * other path quoted.
     */
    private static function withoutPath(string $message, string $xmlFile): string
    {
        $file = basename($xmlFile);

        if ('' === $file) {
            return $message;
        }

        // The name is given back as it is: never read for the references of a replacement.
        $message = (string) preg_replace_callback('#(failed to load external entity )".*"#s', static fn (array $found): string => $found[1].'"'.$file.'"', $message);
        $message = strtr($message, self::namesOf($xmlFile));

        return (string) preg_replace('#"[^"]*/([^"/]+)"#', '"$1"', $message);
    }

    /**
     * The path as given and as the file system resolves it, each standing for the name of
     * the file (strtr() takes the longest first, so that the shorter one never eats a part
     * of the longer).
     *
     * @return array<string, string>
     */
    private static function namesOf(string $path): array
    {
        if ('' === $path) {
            return [];
        }

        $resolvedFolder = realpath(\dirname($path));
        $names = [$path => basename($path)];

        if (false !== $resolvedFolder) {
            $names[$resolvedFolder.\DIRECTORY_SEPARATOR.basename($path)] = basename($path);
        }

        return $names;
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
        $schemaFile = (string) $xsdFile->getRealPath();
        // What a message of libxml may quote of the server: the schema, and the
        // descriptor, each by its name.
        $names = [...self::namesOf($schemaFile), ...self::namesOf((string) $dom->documentURI)];

        return array_map(
            static fn (string $error): string => 'XML error "'.strtr($error, $names).'"',
            XmlDescriptor::schemaErrors($dom, $schemaFile),
        );
    }

    public function getDescriptor($xml_file): \SimpleXMLElement|false
    {
        $this->validate($xml_file);

        return @simplexml_load_file($xml_file, \SimpleXMLElement::class, \LIBXML_NONET);
    }
}
