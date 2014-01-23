<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\DependencyInjection\Loader;

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

/**
 *
 * Load, read and validate config xml files
 *
 * Class XmlFileLoader
 * @package Thelia\Core\DependencyInjection\Loader
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class XmlFileLoader extends FileLoader
{
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

    /**
     * Parses an individual Definition
     *
     * @param string           $id
     * @param SimpleXMLElement $service
     * @param string           $file
     */
    protected function parseDefinition($id, $service, $file)
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

        $this->container->setDefinition($id, $definition);
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
        $schemaLocations = array('http://thelia.net/schema/dic/config' => str_replace('\\', '/',__DIR__.'/schema/dic/config/thelia-1.0.xsd'));

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
