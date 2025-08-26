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

namespace Thelia\Core\DependencyInjection\Loader;

use Propel\Runtime\Propel;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Thelia\Core\TheliaKernel;
use Thelia\Log\Tlog;
use Thelia\Model\Export;
use Thelia\Model\ExportCategory;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Import;
use Thelia\Model\ImportCategory;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;
use Thelia\Model\Map\ExportCategoryTableMap;
use Thelia\Model\Map\ExportTableMap;
use Thelia\Model\Map\ImportCategoryTableMap;
use Thelia\Model\Map\ImportTableMap;

/**
 * Load, read and validate config xml files.
 *
 * Class XmlFileLoader
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class XmlFileLoader extends FileLoader
{
    public const DEFAULT_HOOK_CLASS = 'Thelia\\Core\\Hook\\DefaultHook';

    public function load(mixed $resource, ?string $type = null): void
    {
        $path = $this->locator->locate($resource);

        $xml = $this->parseFile($path);
        $xml->registerXPathNamespace('config', 'http://thelia.net/schema/dic/config');

        $this->removeScope($xml);

        $this->container->addResource(new FileResource($path));

        $this->parseLoops($xml);

        $this->parseFilters($xml);

        $this->parseTemplateDirectives($xml);

        $this->parseParameters($xml);

        $this->parseCommands($xml);

        $this->parseForms($xml);

        $this->parseDefinitions($xml, $path);

        $this->parseHooks($xml, $path, $type);

        $this->propelOnlyRun(
            [$this, 'parseExportCategories'],
            $xml,
        );

        $this->propelOnlyRun(
            [$this, 'parseExports'],
            $xml,
        );

        $this->propelOnlyRun(
            [$this, 'parseImportCategories'],
            $xml,
        );

        $this->propelOnlyRun(
            [$this, 'parseImports'],
            $xml,
        );
    }

    public function propelOnlyRun(callable $method, $arg): void
    {
        if (TheliaKernel::isInstalled()) {
            \call_user_func($method, $arg);
        }
    }

    protected function parseCommands(\SimpleXMLElement $xml): void
    {
        if (false === $commands = $xml->xpath('//config:commands/config:command')) {
            return;
        }

        try {
            $commandConfig = $this->container->getParameter('command.definition');
        } catch (ParameterNotFoundException) {
            $commandConfig = [];
        }

        foreach ($commands as $command) {
            $commandConfig[] = $this->getAttributeAsPhp($command, 'class');
        }

        $this->container->setParameter('command.definition', $commandConfig);
    }

    /**
     * Parses parameters.
     */
    protected function parseParameters(\SimpleXMLElement $xml): void
    {
        if (!$xml->parameters) {
            return;
        }

        $this->container->getParameterBag()->add($this->getArgumentsAsPhp($xml->parameters, 'parameter'));
    }

    /**
     * parse Loops property.
     */
    protected function parseLoops(\SimpleXMLElement $xml): void
    {
        if (false === $loops = $xml->xpath('//config:loops/config:loop')) {
            return;
        }

        try {
            $loopConfig = $this->container->getParameter('Thelia.parser.loops');
        } catch (ParameterNotFoundException) {
            $loopConfig = [];
        }

        foreach ($loops as $loop) {
            $loopConfig[$this->getAttributeAsPhp($loop, 'name')] = $this->getAttributeAsPhp($loop, 'class');
        }

        $this->container->setParameter('Thelia.parser.loops', $loopConfig);
    }

    protected function parseForms(\SimpleXMLElement $xml): void
    {
        if (false === $forms = $xml->xpath('//config:forms/config:form')) {
            return;
        }

        try {
            $formConfig = $this->container->getParameter('Thelia.parser.forms');
        } catch (ParameterNotFoundException) {
            $formConfig = [];
        }

        foreach ($forms as $form) {
            $formConfig[$this->getAttributeAsPhp($form, 'name')] = $this->getAttributeAsPhp($form, 'class');
        }

        $this->container->setParameter('Thelia.parser.forms', $formConfig);
    }

    /**
     * parse Filters property.
     */
    protected function parseFilters(\SimpleXMLElement $xml): void
    {
        if (false === $filters = $xml->xpath('//config:filters/config:filter')) {
            return;
        }

        try {
            $filterConfig = $this->container->getParameter('Thelia.parser.filters');
        } catch (ParameterNotFoundException) {
            $filterConfig = [];
        }

        foreach ($filters as $filter) {
            $filterConfig[$this->getAttributeAsPhp($filter, 'name')] = $this->getAttributeAsPhp($filter, 'class');
        }

        $this->container->setParameter('Thelia.parser.filters', $filterConfig);
    }

    /**
     * parse BaseParams property.
     */
    protected function parseTemplateDirectives(\SimpleXMLElement $xml): void
    {
        if (false === $baseParams = $xml->xpath('//config:templateDirectives/config:templateDirective')) {
            return;
        }

        try {
            $baseParamConfig = $this->container->getParameter('Thelia.parser.templateDirectives');
        } catch (ParameterNotFoundException) {
            $baseParamConfig = [];
        }

        foreach ($baseParams as $baseParam) {
            $baseParamConfig[$this->getAttributeAsPhp($baseParam, 'name')] = $this->getAttributeAsPhp($baseParam, 'class');
        }

        $this->container->setParameter('Thelia.parser.templateDirectives', $baseParamConfig);
    }

    /**
     * Parses multiple definitions.
     */
    protected function parseDefinitions(\SimpleXMLElement $xml, string $file): void
    {
        if (false === $services = $xml->xpath('//config:services/config:service')) {
            return;
        }

        foreach ($services as $service) {
            $this->parseDefinition((string) $service['id'], $service, $file);
        }
    }

    protected function parseDefinition(string $id, \SimpleXMLElement $service, string $file): void
    {
        $definition = $this->parseService($id, $service, $file);

        if ($definition instanceof Definition) {
            $this->container->setDefinition($id, $definition);
        }
    }

    /**
     * Parses multiple definitions.
     */
    protected function parseHooks(\SimpleXMLElement $xml, string $file, string $type): void
    {
        if (false === $hooks = $xml->xpath('//config:hooks/config:hook')) {
            return;
        }

        foreach ($hooks as $hook) {
            $this->parseHook((string) $hook['id'], $hook, $file, $type);
        }
    }

    protected function parseHook(string $id, $hook, string $file, $type): void
    {
        if (!isset($hook['class'])) {
            $hook['class'] = self::DEFAULT_HOOK_CLASS;
        }

        $definition = $this->parseService($id, $hook, $file);

        if ($definition instanceof Definition) {
            if (null !== $type) {
                // inject the BaseModule
                $definition->setProperty('module', new Reference($type));
            }

            $definition->setProperty('parser', new Reference('thelia.parser'));
            $definition->setProperty('translator', new Reference('thelia.translator'));
            $definition->setProperty('assetsResolver', new Reference('thelia.parser.asset.resolver'));
            $definition->setProperty('parserResolver', new Reference('thelia.parser.resolver'));
            $definition->setProperty('dispatcher', new Reference('event_dispatcher'));
            $definition->setPublic(true);
            $this->container->setDefinition($id, $definition);
        }
    }

    /**
     * Parses an individual Definition.
     *
     * @return Definition
     */
    protected function parseService(string $id, \SimpleXMLElement $service, string $file): Definition|ChildDefinition|null
    {
        if ('' !== (string) $service['alias'] && '0' !== (string) $service['alias']) {
            $public = true;

            if (isset($service['public'])) {
                $public = $this->getAttributeAsPhp($service, 'public');
            }

            $this->container->setAlias($id, new Alias((string) $service['alias'], $public));

            return null;
        }

        if (isset($service['parent'])) {
            $definition = new ChildDefinition((string) $service['parent']);
        } else {
            $definition = new Definition();
        }

        $definition->setClass((string) $this->getAttributeAsPhp($service, 'class'))
            ->setShared((bool) $this->getAttributeAsPhp($service, 'shared'))
            ->setPublic((bool) $this->getAttributeAsPhp($service, 'public'))
            ->setFactory($this->getAttributeAsPhp($service, 'factory'))
            ->setSynthetic((bool) $this->getAttributeAsPhp($service, 'synthetic'))
            ->setAbstract((bool) $this->getAttributeAsPhp($service, 'abstract'));

        if ($service->file) {
            $definition->setFile((string) $service->file);
        }

        if (isset($service['decorates'])) {
            $priority = isset($service['decoration-priority']) ? $this->getAttributeAsPhp($service, 'decorates') : 0;
            $definition->setDecoratedService((string) $this->getAttributeAsPhp($service, 'decorates'), null, (int) $priority);
        }

        $definition->setArguments($this->getArgumentsAsPhp($service, 'argument'));
        $definition->setProperties($this->getArgumentsAsPhp($service, 'property'));

        if ([] !== $this->getArgumentsAsPhp($service, 'factory')) {
            $definition->setFactory($this->getServiceFactory($service->factory));
        }

        if (property_exists($service, 'configurator') && null !== $service->configurator) {
            if (isset($service->configurator['function'])) {
                $definition->setConfigurator((string) $service->configurator['function']);
            } else {
                if (isset($service->configurator['service'])) {
                    $class = new Reference((string) $service->configurator['service'], ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);
                } else {
                    $class = (string) $service->configurator['class'];
                }

                $definition->setConfigurator([$class, (string) $service->configurator['method']]);
            }
        }

        foreach ($service->call as $call) {
            $definition->addMethodCall((string) $call['method'], $this->getArgumentsAsPhp($call, 'argument'));
        }

        foreach ($service->tag as $tag) {
            $parameters = [];

            foreach ($tag->attributes() as $name => $value) {
                if ('name' === $name) {
                    continue;
                }

                $parameters[$name] = XmlUtils::phpize($value);
            }

            $definition->addTag((string) $tag['name'], $parameters);
        }

        return $definition;
    }

    protected function parseExportCategories(\SimpleXMLElement $xml): void
    {
        if (false === $exportCategories = $xml->xpath('//config:export_categories/config:export_category')) {
            return;
        }

        $con = Propel::getWriteConnection(ExportCategoryTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            /** @var \SimpleXMLElement $exportCategory */
            foreach ($exportCategories as $exportCategory) {
                $id = (string) $this->getAttributeAsPhp($exportCategory, 'id');

                $exportCategoryModel = ExportCategoryQuery::create()->findOneByRef($id);

                if (null === $exportCategoryModel) {
                    $exportCategoryModel = new ExportCategory();
                    $exportCategoryModel
                        ->setRef($id)
                        ->save($con);
                }

                /** @var \SimpleXMLElement $child */
                foreach ($exportCategory->children() as $child) {
                    $locale = (string) $this->getAttributeAsPhp($child, 'locale');
                    $value = (string) $child;

                    $exportCategoryModel
                        ->setLocale($locale)
                        ->setTitle($value)
                        ->save($con);
                }
            }

            $con->commit();
        } catch (\Exception $exception) {
            $con->rollBack();

            Tlog::getInstance()->error($exception->getMessage());
        }
    }

    protected function parseExports(\SimpleXMLElement $xml): void
    {
        if (false === $exports = $xml->xpath('//config:exports/config:export')) {
            return;
        }

        $con = Propel::getWriteConnection(ExportTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            /** @var \SimpleXMLElement $export */
            foreach ($exports as $export) {
                $id = (string) $this->getAttributeAsPhp($export, 'id');
                $class = (string) $this->getAttributeAsPhp($export, 'class');
                $categoryRef = (string) $this->getAttributeAsPhp($export, 'category_id');

                if (!class_exists($class)) {
                    throw new \ErrorException(\sprintf("The class \"%s\" doesn't exist", $class));
                }

                $category = ExportCategoryQuery::create()->findOneByRef($categoryRef);

                if (null === $category) {
                    throw new \ErrorException(\sprintf("The export category \"%s\" doesn't exist", $categoryRef));
                }

                $exportModel = ExportQuery::create()->findOneByRef($id);

                if (null === $exportModel) {
                    $exportModel = new Export();
                    $exportModel
                        ->setRef($id);
                }

                $exportModel
                    ->setExportCategory($category)
                    ->setHandleClass($class)
                    ->save($con);

                /** @var \SimpleXMLElement $descriptive */
                foreach ($export->children() as $descriptive) {
                    $locale = $this->getAttributeAsPhp($descriptive, 'locale');
                    $title = null;
                    $description = null;

                    /** @var \SimpleXMLElement $row */
                    foreach ($descriptive->children() as $row) {
                        switch ($row->getName()) {
                            case 'title':
                                $title = (string) $row;
                                break;
                            case 'description':
                                $description = (string) $row;
                                break;
                        }
                    }

                    $exportModel
                        ->setLocale($locale)
                        ->setTitle($title)
                        ->setDescription($description)
                        ->save($con);
                }
            }

            $con->commit();
        } catch (\Exception $exception) {
            $con->rollBack();

            Tlog::getInstance()->error($exception->getMessage());
        }
    }

    protected function parseImportCategories(\SimpleXMLElement $xml): void
    {
        if (false === $importCategories = $xml->xpath('//config:import_categories/config:import_category')) {
            return;
        }

        $con = Propel::getWriteConnection(ImportCategoryTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            /** @var \SimpleXMLElement $importCategory */
            foreach ($importCategories as $importCategory) {
                $id = (string) $this->getAttributeAsPhp($importCategory, 'id');

                $importCategoryModel = ImportCategoryQuery::create()->findOneByRef($id);

                if (null === $importCategoryModel) {
                    $importCategoryModel = new ImportCategory();
                    $importCategoryModel
                        ->setRef($id)
                        ->save($con);
                }

                /** @var \SimpleXMLElement $child */
                foreach ($importCategory->children() as $child) {
                    $locale = (string) $this->getAttributeAsPhp($child, 'locale');
                    $value = (string) $child;

                    $importCategoryModel
                        ->setLocale($locale)
                        ->setTitle($value)
                        ->save($con);
                }
            }

            $con->commit();
        } catch (\Exception $exception) {
            $con->rollBack();

            Tlog::getInstance()->error($exception->getMessage());
        }
    }

    protected function parseImports(\SimpleXMLElement $xml): void
    {
        if (false === $imports = $xml->xpath('//config:imports/config:import')) {
            return;
        }

        $con = Propel::getWriteConnection(ImportTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            /** @var \SimpleXMLElement $import */
            foreach ($imports as $import) {
                $id = (string) $this->getAttributeAsPhp($import, 'id');
                $class = (string) $this->getAttributeAsPhp($import, 'class');
                $categoryRef = (string) $this->getAttributeAsPhp($import, 'category_id');

                if (!class_exists($class)) {
                    throw new \ErrorException(\sprintf("The class \"%s\" doesn't exist", $class));
                }

                $category = ImportCategoryQuery::create()->findOneByRef($categoryRef);

                if (null === $category) {
                    throw new \ErrorException(\sprintf("The import category \"%s\" doesn't exist", $categoryRef));
                }

                $importModel = ImportQuery::create()->findOneByRef($id);

                if (null === $importModel) {
                    $importModel = new Import();
                    $importModel
                        ->setRef($id);
                }

                $importModel
                    ->setImportCategory($category)
                    ->setHandleClass($class)
                    ->save($con);

                /** @var \SimpleXMLElement $descriptive */
                foreach ($import->children() as $descriptive) {
                    $locale = $this->getAttributeAsPhp($descriptive, 'locale');
                    $title = null;
                    $description = null;

                    /** @var \SimpleXMLElement $row */
                    foreach ($descriptive->children() as $row) {
                        switch ($row->getName()) {
                            case 'title':
                                $title = (string) $row;
                                break;
                            case 'description':
                                $description = (string) $row;
                                break;
                        }
                    }

                    $importModel
                        ->setLocale($locale)
                        ->setTitle($title)
                        ->setDescription($description)
                        ->save($con);
                }
            }

            $con->commit();
        } catch (\Exception $exception) {
            $con->rollBack();

            Tlog::getInstance()->error($exception->getMessage());
        }
    }

    /**
     * Parses a XML file.
     *
     * @param string $file Path to a file
     *
     * @throws InvalidArgumentException When loading of XML file returns error
     */
    protected function parseFile(string $file): ?\SimpleXMLElement
    {
        try {
            $dom = XmlUtils::loadFile($file, $this->validateSchema(...));
        } catch (\InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
        }

        return simplexml_import_dom($dom);
    }

    /**
     * Validates a documents XML schema.
     *
     * @throws RuntimeException When extension references a non-existent XSD file
     */
    public function validateSchema(\DOMDocument $dom): bool
    {
        $schemaLocations = ['http://thelia.net/schema/dic/config' => str_replace('\\', '/', __DIR__.'/schema/dic/config/thelia-1.0.xsd')];

        $tmpfiles = [];
        $imports = '';

        foreach ($schemaLocations as $namespace => $location) {
            $parts = explode('/', $location);

            if (0 === stripos($location, 'phar://')) {
                $tmpfile = tempnam(sys_get_temp_dir(), 'sf2');

                if ($tmpfile) {
                    copy($location, $tmpfile);
                    $tmpfiles[] = $tmpfile;
                    $parts = explode('/', str_replace('\\', '/', $tmpfile));
                }
            }

            $drive = '\\' === \DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
            $location = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));

            $imports .= \sprintf('  <xsd:import namespace="%s" schemaLocation="%s" />'."\n", $namespace, $location);
        }

        $source = <<<EOF
            <?xml version="1.0" encoding="utf-8" ?>
            <xsd:schema xmlns="http://symfony.com/schema"
                xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                targetNamespace="http://symfony.com/schema"
                elementFormDefault="qualified">

                <xsd:import namespace="http://www.w3.org/XML/1998/namespace"/>
            {$imports}
            </xsd:schema>
            EOF;

        $valid = @$dom->schemaValidateSource($source);

        foreach ($tmpfiles as $tmpfile) {
            @unlink($tmpfile);
        }

        return $valid;
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return bool true if this class supports the given resource, false otherwise
     */
    public function supports(mixed $resource, ?string $type = null): bool
    {
        // TODO: Implement supports() method.
    }

    /**
     * Returns arguments as valid PHP types.
     */
    private function getArgumentsAsPhp(\SimpleXMLElement $xml, $name, bool $lowercase = true): array
    {
        $arguments = [];

        foreach ($xml->{$name} as $arg) {
            if (isset($arg['name'])) {
                $arg['key'] = (string) $arg['name'];
            }

            $key = isset($arg['key']) ? (string) $arg['key'] : ([] === $arguments ? 0 : max(array_keys($arguments)) + 1);

            // parameter keys are case insensitive
            if ('parameter' === $name && $lowercase) {
                $key = strtolower((string) $key);
            }

            // this is used by DefinitionDecorator to overwrite a specific
            // argument of the parent definition
            if (isset($arg['index'])) {
                $key = 'index_'.$arg['index'];
            }

            switch ($arg['type']) {
                case 'service':
                    $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;

                    if (isset($arg['on-invalid']) && 'ignore' === $arg['on-invalid']) {
                        $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
                    } elseif (isset($arg['on-invalid']) && 'null' === $arg['on-invalid']) {
                        $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
                    }

                    $arguments[$key] = new Reference((string) $arg['id'], $invalidBehavior);
                    break;
                case 'expression':
                    $arguments[$key] = new Expression((string) $arg);
                    break;
                case 'collection':
                    $arguments[$key] = $this->getArgumentsAsPhp($arg, $name, false);
                    break;
                case 'string':
                    $arguments[$key] = (string) $arg;
                    break;
                case 'constant':
                    $arguments[$key] = \constant((string) $arg);
                    break;
                default:
                    $arguments[$key] = XmlUtils::phpize($arg);
            }
        }

        return $arguments;
    }

    protected function getServiceFactory($factoryXml)
    {
        $factoryMethod = $this->getAttributeAsPhp($factoryXml, 'method');

        if ('' === $factoryMethod) {
            $factoryMethod = '__invoke';
        }

        if ('' !== $this->getAttributeAsPhp($factoryXml, 'service')) {
            return [
                new Reference($this->getAttributeAsPhp($factoryXml, 'service')),
                $factoryMethod,
            ];
        }

        if ('' !== $this->getAttributeAsPhp($factoryXml, 'class')) {
            return [
                $this->getAttributeAsPhp($factoryXml, 'class'),
                $factoryMethod,
            ];
        }

        throw new \ErrorException('You must specify either a class or a service in factory');
    }

    public function getAttributeAsPhp(\SimpleXMLElement $xml, $name): mixed
    {
        if (!isset($xml[$name]) || empty($xml[$name])) {
            return null;
        }

        return XmlUtils::phpize($xml[$name]);
    }

    private function removeScope(\SimpleXMLElement $xml): void
    {
        $nodes = $xml->xpath('//*[@scope]');

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            unset($node['scope']);
        }
    }
}
