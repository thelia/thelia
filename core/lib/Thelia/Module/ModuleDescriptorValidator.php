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

use DOMDocument;
use SplFileInfo;
use ErrorException;
use SimpleXMLElement;
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
        $dom = new DOMDocument();
        $errors = [];

        if ($dom->load($xml_file)) {
            /** @var SplFileInfo $xsdFile */
            foreach ($this->xsdFinder as $xsdFile) {
                $xsdVersion = array_search($xsdFile->getBasename(), self::$versions, true);

                if (false === $xsdVersion || (null !== $version && $version != $xsdVersion)) {
                    continue;
                }

                $errors = $this->schemaValidate($dom, $xsdFile);

                if ($errors === []) {
                    $this->moduleVersion = $xsdVersion;

                    return true;
                }
            }
        }

        throw new InvalidXmlDocumentException(
            sprintf(
                '%s file is not a valid file : %s',
                $xml_file,
                implode(', ', $errors)
            )
        );
    }

    /**
     * Validate the schema of a XML file with a given xsd file.
     *
     * @param DOMDocument $dom The XML document
     * @param SplFileInfo $xsdFile The XSD file
     *
     * @return array an array of errors if validation fails, otherwise an empty array
     */
    protected function schemaValidate(DOMDocument $dom, SplFileInfo $xsdFile): array
    {
        $errorMessages = [];

        try {
            libxml_use_internal_errors(true);

            if (!$dom->schemaValidate($xsdFile->getRealPath())) {
                $errors = libxml_get_errors();

                foreach ($errors as $error) {
                    $errorMessages[] = sprintf(
                        'XML error "%s" [%d] (Code %d) in %s on line %d column %d'."\n",
                        $error->message,
                        $error->level,
                        $error->code,
                        $error->file,
                        $error->line,
                        $error->column
                    );
                }

                libxml_clear_errors();
            }

            libxml_use_internal_errors(false);
        } catch (ErrorException) {
            libxml_use_internal_errors(false);
        }

        return $errorMessages;
    }

    public function getDescriptor($xml_file): SimpleXMLElement|false
    {
        $this->validate($xml_file);

        return @simplexml_load_file($xml_file);
    }
}
