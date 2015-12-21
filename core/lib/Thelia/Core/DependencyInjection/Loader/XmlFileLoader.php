<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\DependencyInjection\Loader;

use Propel\Runtime\Propel;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\SimpleXMLElement;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Thelia\Core\Thelia;
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
 *
 * Load, read and validate config xml files
 *
 * Class XmlFileLoader
 * @package Thelia\Core\DependencyInjection\Loader
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class XmlFileLoader extends FileLoader
{
    const DEFAULT_HOOK_CLASS = "Thelia\\Core\\Hook\\DefaultHook";
    /**
     * Loads an XML file.
     *
     * @param mixed  $file The resource
     * @param string $type The resource type
     */
    public function load($file, $type = null)
    {
        $path = $this->locator->locate($file);

        $xml = $this->parseFile($path);
        $xml->registerXPathNamespace('config', 'http://thelia.net/schema/dic/config');

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
            [$this, "parseExportCategories"],
            $xml
        );

        $this->propelOnlyRun(
            [$this, "parseExports"],
            $xml
        );

        $this->propelOnlyRun(
            [$this, "parseImportCategories"],
            $xml
        );

        $this->propelOnlyRun(
            [$this, "parseImports"],
            $xml
        );
    }

    public function propelOnlyRun(callable $method, $arg)
    {
        if (Thelia::isInstalled()) {
            call_user_func($method, $arg);
        }
    }

    protected function parseCommands(SimpleXMLElement $xml)
    {
        if (false === $commands = $xml->xpath('//config:commands/config:command')) {
            return;
        }
        try {
            $commandConfig = $this->container->getParameter("command.definition");
        } catch (ParameterNotFoundException $e) {
            $commandConfig = array();
        }

        foreach ($commands as $command) {
            array_push($commandConfig, $command->getAttributeAsPhp("class"));
        }

        $this->container->setParameter("command.definition", $commandConfig);
    }

    /**
     * Parses parameters
     *
     * @param SimpleXMLElement $xml
     */
    protected function parseParameters(SimpleXMLElement $xml)
    {
        if (!$xml->parameters) {
            return;
        }

        $this->container->getParameterBag()->add($xml->parameters->getArgumentsAsPhp('parameter'));
    }

    /**
     *
     * parse Loops property
     *
     * @param SimpleXMLElement $xml
     */
    protected function parseLoops(SimpleXMLElement $xml)
    {
        if (false === $loops = $xml->xpath('//config:loops/config:loop')) {
            return;
        }
        try {
            $loopConfig = $this->container->getParameter("Thelia.parser.loops");
        } catch (ParameterNotFoundException $e) {
            $loopConfig = array();
        }

        foreach ($loops as $loop) {
            $loopConfig[$loop->getAttributeAsPhp("name")] = $loop->getAttributeAsPhp("class");
        }

        $this->container->setParameter("Thelia.parser.loops", $loopConfig);
    }

    protected function parseForms(SimpleXMLElement $xml)
    {
        if (false === $forms = $xml->xpath('//config:forms/config:form')) {
            return;
        }

        try {
            $formConfig = $this->container->getParameter("Thelia.parser.forms");
        } catch (ParameterNotFoundException $e) {
            $formConfig = array();
        }

        foreach ($forms as $form) {
            $formConfig[$form->getAttributeAsPhp('name')] = $form->getAttributeAsPhp('class');
        }

        $this->container->setParameter('Thelia.parser.forms', $formConfig);
    }

    /**
     * parse Filters property
     *
     * @param SimpleXMLElement $xml
     */
    protected function parseFilters(SimpleXMLElement $xml)
    {
        if (false === $filters = $xml->xpath('//config:filters/config:filter')) {
            return;
        }
        try {
            $filterConfig = $this->container->getParameter("Thelia.parser.filters");
        } catch (ParameterNotFoundException $e) {
            $filterConfig = array();
        }

        foreach ($filters as $filter) {
            $filterConfig[$filter->getAttributeAsPhp("name")] = $filter->getAttributeAsPhp("class");
        }

        $this->container->setParameter("Thelia.parser.filters", $filterConfig);
    }

    /**
     * parse BaseParams property
     *
     * @param SimpleXMLElement $xml
     */
    protected function parseTemplateDirectives(SimpleXMLElement $xml)
    {
        if (false === $baseParams = $xml->xpath('//config:templateDirectives/config:templateDirective')) {
            return;
        }
        try {
            $baseParamConfig = $this->container->getParameter("Thelia.parser.templateDirectives");
        } catch (ParameterNotFoundException $e) {
            $baseParamConfig = array();
        }

        foreach ($baseParams as $baseParam) {
            $baseParamConfig[$baseParam->getAttributeAsPhp("name")] = $baseParam->getAttributeAsPhp("class");
        }

        $this->container->setParameter("Thelia.parser.templateDirectives", $baseParamConfig);
    }

    /**
     * Parses multiple definitions
     *
     * @param SimpleXMLElement $xml
     * @param string           $file
     */
    protected function parseDefinitions(SimpleXMLElement $xml, $file)
    {
        if (false === $services = $xml->xpath('//config:services/config:service')) {
            return;
        }
        foreach ($services as $service) {
            $this->parseDefinition((string) $service['id'], $service, $file);
        }
    }

    protected function parseDefinition($id, $service, $file)
    {
        $definition = $this->parseService($id, $service, $file);
        if (null !== $definition) {
            $this->container->setDefinition($id, $definition);
        }
    }

    /**
     * Parses multiple definitions
     *
     * @param SimpleXMLElement $xml
     * @param string           $file
     * @param string           $type
     */
    protected function parseHooks(SimpleXMLElement $xml, $file, $type)
    {
        if (false === $hooks = $xml->xpath('//config:hooks/config:hook')) {
            return;
        }
        foreach ($hooks as $hook) {
            $this->parseHook((string) $hook['id'], $hook, $file, $type);
        }
    }

    protected function parseHook($id, $hook, $file, $type)
    {
        if (!array_key_exists('scope', $hook)) {
            $hook['scope'] = 'request';
        }

        if (! isset($hook['class'])) {
            $hook['class'] = self::DEFAULT_HOOK_CLASS;
        }

        $definition = $this->parseService($id, $hook, $file);
        if (null !== $definition) {
            if (null !== $type) {
                // inject the BaseModule
                $definition->setProperty('module', new Reference($type));
            }
            $definition->setProperty('parser', new Reference('thelia.parser'));
            $definition->setProperty('translator', new Reference('thelia.translator'));
            $definition->setProperty('assetsResolver', new Reference('thelia.parser.asset.resolver'));
            $definition->setProperty('dispatcher', new Reference('event_dispatcher'));
            $this->container->setDefinition($id, $definition);
        }
    }

    /**
     * Parses an individual Definition
     *
     * @param  string           $id
     * @param  SimpleXMLElement $service
     * @param  string           $file
     * @return Definition
     */
    protected function parseService($id, $service, $file)
    {
        if ((string) $service['alias']) {
            $public = true;
            if (isset($service['public'])) {
                $public = $service->getAttributeAsPhp('public');
            }
            $this->container->setAlias($id, new Alias((string) $service['alias'], $public));

            return;
        }

        if (isset($service['parent'])) {
            $definition = new DefinitionDecorator((string) $service['parent']);
        } else {
            $definition = new Definition();
        }

        foreach (array('class', 'scope', 'public', 'factory-class', 'factory-method', 'factory-service', 'synthetic', 'abstract') as $key) {
            if (isset($service[$key])) {
                $method = 'set'.str_replace('-', '', $key);
                $definition->$method((string) $service->getAttributeAsPhp($key));
            }
        }

        if ($service->file) {
            $definition->setFile((string) $service->file);
        }

        $definition->setArguments($service->getArgumentsAsPhp('argument'));
        $definition->setProperties($service->getArgumentsAsPhp('property'));

        if (isset($service->configurator)) {
            if (isset($service->configurator['function'])) {
                $definition->setConfigurator((string) $service->configurator['function']);
            } else {
                if (isset($service->configurator['service'])) {
                    $class = new Reference((string) $service->configurator['service'], ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false);
                } else {
                    $class = (string) $service->configurator['class'];
                }

                $definition->setConfigurator(array($class, (string) $service->configurator['method']));
            }
        }

        foreach ($service->call as $call) {
            $definition->addMethodCall((string) $call['method'], $call->getArgumentsAsPhp('argument'));
        }

        foreach ($service->tag as $tag) {
            $parameters = array();
            foreach ($tag->attributes() as $name => $value) {
                if ('name' === $name) {
                    continue;
                }

                $parameters[$name] = SimpleXMLElement::phpize($value);
            }

            $definition->addTag((string) $tag['name'], $parameters);
        }

        return $definition;
    }

    protected function parseExportCategories(SimpleXMLElement $xml)
    {
        if (false === $exportCategories = $xml->xpath('//config:export_categories/config:export_category')) {
            return;
        }

        $con = Propel::getWriteConnection(ExportCategoryTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            /** @var SimpleXMLElement $exportCategory */
            foreach ($exportCategories as $exportCategory) {
                $id = (string) $exportCategory->getAttributeAsPhp("id");

                $exportCategoryModel = ExportCategoryQuery::create()->findOneByRef($id);

                if ($exportCategoryModel === null) {
                    $exportCategoryModel = new ExportCategory();
                    $exportCategoryModel
                        ->setRef($id)
                        ->save($con)
                    ;
                }

                /** @var SimpleXMLElement $child */
                foreach ($exportCategory->children() as $child) {
                    $locale = (string) $child->getAttributeAsPhp("locale");
                    $value = (string) $child;

                    $exportCategoryModel
                        ->setLocale($locale)
                        ->setTitle($value)
                        ->save($con);
                    ;
                }
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            Tlog::getInstance()->error($e->getMessage());
        }
    }

    protected function parseExports(SimpleXMLElement $xml)
    {
        if (false === $exports = $xml->xpath('//config:exports/config:export')) {
            return;
        }

        $con = Propel::getWriteConnection(ExportTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            /** @var SimpleXMLElement $export */
            foreach ($exports as $export) {
                $id = (string) $export->getAttributeAsPhp("id");
                $class = (string) $export->getAttributeAsPhp("class");
                $categoryRef = (string) $export->getAttributeAsPhp("category_id");

                if (!class_exists($class)) {
                    throw new \ErrorException(
                        "The class \"$class\" doesn't exist"
                    );
                }

                $category = ExportCategoryQuery::create()->findOneByRef($categoryRef);

                if (null === $category) {
                    throw new \ErrorException(
                        "The export category \"$categoryRef\" doesn't exist"
                    );
                }

                $exportModel = ExportQuery::create()->findOneByRef($id);

                if (null === $exportModel) {
                    $exportModel = new Export();
                    $exportModel
                        ->setRef($id)
                    ;
                }

                $exportModel
                    ->setExportCategory($category)
                    ->setHandleClass($class)
                    ->save($con)
                ;

                /** @var SimpleXMLElement $descriptive */
                foreach ($export->children() as $descriptive) {
                    $locale = $descriptive->getAttributeAsPhp("locale");
                    $title = null;
                    $description = null;

                    /** @var SimpleXMLElement $row */
                    foreach ($descriptive->children() as $row) {
                        switch ($row->getName()) {
                            case "title":
                                $title = (string) $row;
                                break;
                            case "description":
                                $description = (string) $row;
                                break;
                        }
                    }

                    $exportModel
                        ->setLocale($locale)
                        ->setTitle($title)
                        ->setDescription($description)
                        ->save($con)
                    ;
                }
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            Tlog::getInstance()->error($e->getMessage());
        }
    }

    protected function parseImportCategories(SimpleXMLElement $xml)
    {
        if (false === $importCategories = $xml->xpath('//config:import_categories/config:import_category')) {
            return;
        }

        $con = Propel::getWriteConnection(ImportCategoryTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            /** @var SimpleXMLElement $importCategory */
            foreach ($importCategories as $importCategory) {
                $id = (string) $importCategory->getAttributeAsPhp("id");

                $importCategoryModel = ImportCategoryQuery::create()->findOneByRef($id);

                if ($importCategoryModel === null) {
                    $importCategoryModel = new ImportCategory();
                    $importCategoryModel
                        ->setRef($id)
                        ->save($con)
                    ;
                }

                /** @var SimpleXMLElement $child */
                foreach ($importCategory->children() as $child) {
                    $locale = (string) $child->getAttributeAsPhp("locale");
                    $value = (string) $child;

                    $importCategoryModel
                        ->setLocale($locale)
                        ->setTitle($value)
                        ->save($con);
                    ;
                }
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            Tlog::getInstance()->error($e->getMessage());
        }
    }

    protected function parseImports(SimpleXMLElement $xml)
    {
        if (false === $imports = $xml->xpath('//config:imports/config:import')) {
            return;
        }

        $con = Propel::getWriteConnection(ImportTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            /** @var SimpleXMLElement $import */
            foreach ($imports as $import) {
                $id = (string) $import->getAttributeAsPhp("id");
                $class = (string) $import->getAttributeAsPhp("class");
                $categoryRef = (string) $import->getAttributeAsPhp("category_id");

                if (!class_exists($class)) {
                    throw new \ErrorException(
                        "The class \"$class\" doesn't exist"
                    );
                }

                $category = ImportCategoryQuery::create()->findOneByRef($categoryRef);

                if (null === $category) {
                    throw new \ErrorException(
                        "The import category \"$categoryRef\" doesn't exist"
                    );
                }

                $importModel = ImportQuery::create()->findOneByRef($id);

                if (null === $importModel) {
                    $importModel = new Import();
                    $importModel
                        ->setRef($id)
                    ;
                }

                $importModel
                    ->setImportCategory($category)
                    ->setHandleClass($class)
                    ->save($con)
                ;

                /** @var SimpleXMLElement $descriptive */
                foreach ($import->children() as $descriptive) {
                    $locale = $descriptive->getAttributeAsPhp("locale");
                    $title = null;
                    $description = null;

                    /** @var SimpleXMLElement $row */
                    foreach ($descriptive->children() as $row) {
                        switch ($row->getName()) {
                            case "title":
                                $title = (string) $row;
                                break;
                            case "description":
                                $description = (string) $row;
                                break;
                        }
                    }

                    $importModel
                        ->setLocale($locale)
                        ->setTitle($title)
                        ->setDescription($description)
                        ->save($con)
                    ;
                }
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            Tlog::getInstance()->error($e->getMessage());
        }
    }

    /**
     * Parses a XML file.
     *
     * @param string $file Path to a file
     *
     * @return SimpleXMLElement
     *
     * @throws InvalidArgumentException When loading of XML file returns error
     */
    protected function parseFile($file)
    {
        try {
            $dom = XmlUtils::loadFile($file, array($this, 'validateSchema'));
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        return simplexml_import_dom($dom, 'Symfony\\Component\\DependencyInjection\\SimpleXMLElement');
    }

    /**
     * Validates a documents XML schema.
     *
     * @param \DOMDocument $dom
     *
     * @return Boolean
     *
     * @throws RuntimeException When extension references a non-existent XSD file
     */
    public function validateSchema(\DOMDocument $dom)
    {
        $schemaLocations = array('http://thelia.net/schema/dic/config' => str_replace('\\', '/', __DIR__.'/schema/dic/config/thelia-1.0.xsd'));

        $tmpfiles = array();
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
            $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
            $location = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));

            $imports .= sprintf('  <xsd:import namespace="%s" schemaLocation="%s" />'."\n", $namespace, $location);
        }

        $source = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<xsd:schema xmlns="http://symfony.com/schema"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    targetNamespace="http://symfony.com/schema"
    elementFormDefault="qualified">

    <xsd:import namespace="http://www.w3.org/XML/1998/namespace"/>
$imports
</xsd:schema>
EOF
        ;

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
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        // TODO: Implement supports() method.
    }
}
