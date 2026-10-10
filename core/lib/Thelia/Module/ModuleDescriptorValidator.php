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
use Thelia\Tools\TerminalText;

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
        $notLoaded = XmlDescriptor::loadingErrors($dom, (string) $xml_file);

        if ([] !== $notLoaded) {
            $reason = implode(', ', $notLoaded);
        } else {
            ['version' => $this->moduleVersion, 'errors' => $errors] = XmlDescriptor::matchingSchemaVersion($dom, $this->xsdFinder, self::$versions, null === $version ? null : (string) $version, $this->schemaValidate(...));

            if (null !== $this->moduleVersion) {
                return true;
            }

            $reason = implode(', ', $errors);
        }

        // Shown to the administrator who uploads the module: the module it is about, never
        // where the server unpacked it, and nothing a value of the descriptor would make a
        // log or a page obey.
        throw new InvalidXmlDocumentException(TerminalText::onOneLine(\sprintf('The %s is not a valid file: %s', self::describe((string) $xml_file), $reason)));
    }

    /**
     * The descriptor as the administrator knows it: "module.xml of <module>" for one at its
     * place in a module (<module>/Config/module.xml), the file name alone anywhere else.
     */
    private static function describe(string $xmlFile): string
    {
        $file = trim(basename($xmlFile));

        if ('' === $file) {
            return 'descriptor';
        }

        $configFolder = \dirname($xmlFile);

        if ('Config' !== basename($configFolder) || \in_array(basename(\dirname($configFolder)), ['', '.', '..'], true)) {
            return $file;
        }

        return \sprintf('%s of %s', $file, basename(\dirname($configFolder)));
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
        return array_map(
            static fn (string $error): string => 'XML error "'.$error.'"',
            XmlDescriptor::schemaErrors($dom, (string) $xsdFile->getRealPath()),
        );
    }

    public function getDescriptor($xml_file): \SimpleXMLElement|false
    {
        $this->validate($xml_file);

        return XmlDescriptor::read((string) $xml_file);
    }
}
