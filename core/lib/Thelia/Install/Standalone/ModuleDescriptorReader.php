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

namespace Thelia\Install\Standalone;

use Thelia\Module\Exception\InvalidModuleDescriptorException;
use Thelia\Module\Exception\InvalidXmlDocumentException;
use Thelia\Module\ModuleDescriptor;
use Thelia\Module\ModuleDescriptorValidator;
use Thelia\Tools\TerminalText;

/**
 * Reads the module descriptors of the module directories without the kernel and without a
 * database, the way the install registers them: the installers read them before they
 * create anything, so that a descriptor the install refuses stops them first.
 */
final readonly class ModuleDescriptorReader
{
    private const MODULE_TYPE_MAP = [
        'classic' => 1,
        'payment' => 3,
        'delivery' => 2,
    ];

    public function __construct(
        private ModuleDescriptorValidator $validator = new ModuleDescriptorValidator(),
    ) {
    }

    /**
     * Every module directory holding a readable Config/module.xml, in the order the
     * directories are given, then the disk lists them.
     *
     * @param string[] $moduleDirectories
     *
     * @return list<ModuleDescriptorRecord>
     *
     * @throws InvalidModuleDescriptorException on a descriptor the install refuses
     */
    public function read(array $moduleDirectories = [THELIA_MODULE_DIR, THELIA_LOCAL_MODULE_DIR]): array
    {
        $records = [];

        foreach (array_filter($moduleDirectories, 'is_dir') as $baseDir) {
            foreach (new \DirectoryIterator($baseDir) as $entry) {
                if (!$entry->isDir() || $entry->isDot()) {
                    continue;
                }

                $moduleXml = $entry->getPathname().'/Config/module.xml';
                if (!file_exists($moduleXml)) {
                    continue;
                }

                // No network access for an entity or a DTD a descriptor would point at.
                $descriptor = @simplexml_load_file($moduleXml, \SimpleXMLElement::class, \LIBXML_NONET);
                if (false === $descriptor) {
                    continue;
                }

                $code = $entry->getFilename();
                $type = (string) ($descriptor->type ?? 'classic');

                $records[] = new ModuleDescriptorRecord($code, $entry->getPathname(), $descriptor, [
                    'code' => $code,
                    'version' => (string) ($descriptor->version ?? '0.0.1'),
                    'type' => self::MODULE_TYPE_MAP[$type] ?? 1,
                    'category' => $type,
                    'activate' => $this->validatedEnabledByDefault($descriptor, $moduleXml) ? 1 : 0,
                    'namespace' => (string) ($descriptor->fullnamespace ?? $code.'\\'.$code),
                    'mandatory' => (int) ($descriptor->mandatory ?? 0),
                    'hidden' => (int) ($descriptor->hidden ?? 0),
                ]);
            }
        }

        return $records;
    }

    /**
     * The install reads the descriptor without the kernel, so it checks the schema itself
     * when the descriptor carries `<enabled-by-default>`: only the 2.2 format knows the
     * element, as the last one of `<module>`. The later steps that read the descriptor
     * (module:refresh through updateModule(), template:set through ModuleValidator) validate
     * it first, so a descriptor refused there has to be refused here too, or the module would
     * be registered and then fail to refresh or to be installed by a theme. The back-office
     * activation does not validate it: the toggle event skips the check. A descriptor that
     * does not declare the element is read as before, without the schema.
     */
    private function validatedEnabledByDefault(\SimpleXMLElement $descriptor, string $moduleXml): bool
    {
        // The schema rules first, so that a refused value comes back with the message every
        // later step would give; the reader turns what the schema accepted, or the absence of
        // the element, into a boolean.
        if (0 !== \count($descriptor->{ModuleDescriptor::ENABLED_BY_DEFAULT})) {
            try {
                $this->validator->validate($moduleXml);
            } catch (InvalidXmlDocumentException $exception) {
                throw new InvalidModuleDescriptorException(\sprintf('The descriptor %s declares <%s> and is refused by the module schema, which accepts the element once, as the last element of a 2.2 descriptor, with the value 0 or 1. %s', TerminalText::singleLine($moduleXml), ModuleDescriptor::ENABLED_BY_DEFAULT, TerminalText::onOneLine($exception->getMessage())), 0, $exception);
            }
        }

        return ModuleDescriptor::enabledByDefault($descriptor, $moduleXml);
    }
}
