<?php

namespace Thelia\Core\Template\Smarty;

use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\EventDispatcher\EventDispatcherInterface;

use \Smarty;


use Thelia\Core\Template\ParserInterface;


use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TemplateDefinition;
use Imagine\Exception\InvalidArgumentException;
use Thelia\Core\Translation\Translator;

/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class SmartyParser extends Smarty implements ParserInterface
{
    public $plugins = array();

    protected $request;
    protected $dispatcher;
    protected $parserContext;

    protected $backOfficeTemplateDirectories = array();
    protected $frontOfficeTemplateDirectories = array();

    protected $templateDirectories = array();

    /**
     * @var TemplateDefinition
     */
    protected $templateDefinition = "";

    protected $status = 200;

    /**
     * @param Request                  $request
     * @param EventDispatcherInterface $dispatcher
     * @param ParserContext            $parserContext
     * @param string                   $env
     * @param bool                     $debug
     */
    public function __construct(
        Request $request, EventDispatcherInterface $dispatcher, ParserContext $parserContext,
        $env = "prod", $debug = false)
    {
        parent::__construct();

        $this->request = $request;
        $this->dispatcher = $dispatcher;
        $this->parserContext = $parserContext;

        // Configure basic Smarty parameters

        $compile_dir = THELIA_ROOT . 'cache/'. $env .'/smarty/compile';
        if (! is_dir($compile_dir)) @mkdir($compile_dir, 0777, true);

        $cache_dir = THELIA_ROOT . 'cache/'. $env .'/smarty/cache';
        if (! is_dir($cache_dir)) @mkdir($cache_dir, 0777, true);

        $this->setCompileDir($compile_dir);
        $this->setCacheDir($cache_dir);

        $this->debugging = $debug;

        // Prevent smarty ErrorException: Notice: Undefined index bla bla bla...
        $this->error_reporting = E_ALL ^ E_NOTICE;

        // Si on n'est pas en mode debug, activer le cache, avec une lifetime de 15mn, et en vérifiant que les templates sources n'ont pas été modifiés.

        if ($debug) {
            $this->setCaching(Smarty::CACHING_OFF);
            $this->setForceCompile(true);
        } else {
            $this->setForceCompile(false);
        }

        //$this->enableSecurity();

        // The default HTTP status
        $this->status = 200;

        $this->registerFilter('output', array($this, "removeBlankLines"));
        $this->registerFilter('variable', array(__CLASS__, "theliaEscape"));
    }

    /**
     * Add a template directory to the current template list
     *
     * @param unknown $templateType      the template type (a TemplateDefinition type constant)
     * @param string  $templateName      the template name
     * @param string  $templateDirectory path to the template dirtectory
     * @param unknown $key               ???
     * @param string  $unshift           ??? Etienne ?
     */
    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false)
    {
        if (true === $unshift && isset($this->templateDirectories[$templateType][$templateName])) {

            $this->templateDirectories[$templateType][$templateName] = array_merge(
                array(
                    $key => $templateDirectory,
                ),
                $this->templateDirectories[$templateType][$templateName]
            );
        } else {
            $this->templateDirectories[$templateType][$templateName][$key] = $templateDirectory;
        }
    }

    /**
     * Return the registeted template directories for a givent template type
     *
     * @param  unknown                  $templateType
     * @throws InvalidArgumentException
     * @return multitype:
     */
    public function getTemplateDirectories($templateType)
    {
        if (! isset($this->templateDirectories[$templateType])) {
            throw new InvalidArgumentException("Failed to get template type %", $templateType);
        }

        return $this->templateDirectories[$templateType];
    }

    public function removeBlankLines($tpl_source, \Smarty_Internal_Template $template)
    {
        return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $tpl_source);
    }

    public static function theliaEscape($content, $smarty)
    {
        if (is_scalar($content)) {
            return htmlspecialchars($content, ENT_QUOTES, Smarty::$_CHARSET);
        } else {
            return $content;
        }
    }

    /**
     * @param TemplateDefinition $templateDefinition
     */
    public function setTemplateDefinition(TemplateDefinition $templateDefinition)
    {
        $this->templateDefinition = $templateDefinition;

        /* init template directories */
        $this->setTemplateDir(array());

        /* define config directory */
        $configDirectory = THELIA_TEMPLATE_DIR . $this->getTemplate() . '/configs';
        $this->setConfigDir($configDirectory);

        /* add modules template directories */
        $this->addTemplateDirectory(
            $templateDefinition->getType(),
            $templateDefinition->getName(),
            THELIA_TEMPLATE_DIR . $this->getTemplate(),
            '0',
            true
        );

        /* do not pass array directly to addTemplateDir since we cant control on keys */
        if (isset($this->templateDirectories[$templateDefinition->getType()][$templateDefinition->getName()])) {
            foreach ($this->templateDirectories[$templateDefinition->getType()][$templateDefinition->getName()] as $key => $directory) {
                $this->addTemplateDir($directory, $key);
            }
        }
    }

    /**
     * Get template definition
     *
     * @param bool $webAssetTemplate Allow to load asset from another template
     *                               If the name of the template if provided
     *
     * @return TemplateDefinition
     */
    public function getTemplateDefinition($webAssetTemplate = false)
    {
        $ret = $this->templateDefinition;
        if ($webAssetTemplate) {
            $customPath = str_replace($ret->getName(), $webAssetTemplate, $ret->getPath());
            $ret->setName($webAssetTemplate);
            $ret->setPath($customPath);
        }

        return $ret;
    }

    public function getTemplate()
    {
        return $this->templateDefinition->getPath();
    }
    /**
     * Return a rendered template, either from file or ftom a string
     *
     * @param string $resourceType    either 'string' (rendering from a string) or 'file' (rendering a file)
     * @param string $resourceContent the resource content (a text, or a template file name)
     * @param array  $parameters      an associative array of names / value pairs
     *
     * @return string the rendered template text
     */
    protected function internalRenderer($resourceType, $resourceContent, array $parameters)
    {
        // Assign the parserContext variables
        foreach ($this->parserContext as $var => $value) {
            $this->assign($var, $value);
        }

        $this->assign($parameters);

        return $this->fetch(sprintf("%s:%s", $resourceType, $resourceContent));
    }

    /**
     * Return a rendered template file
     *
     * @param  string $realTemplateName the template name (from the template directory)
     * @param  array  $parameters       an associative array of names / value pairs
     * @return string the rendered template text
     */
    public function render($realTemplateName, array $parameters = array())
    {
        if (false === $this->templateExists($realTemplateName)) {
            throw new ResourceNotFoundException(Translator::getInstance()->trans("Template file %file cannot be found.", array('%file' => $realTemplateName)));
        }

        return $this->internalRenderer('file', $realTemplateName, $parameters);
    }

    /**
     * Return a rendered template text
     *
     * @param  string $templateText the template text
     * @param  array  $parameters   an associative array of names / value pairs
     * @return string the rendered template text
     */
    public function renderString($templateText, array $parameters = array())
    {
        return $this->internalRenderer('string', $templateText, $parameters);
    }

    /**
     *
     * set $content with the body of the response or the Response object directly
     *
     * @param string|Thelia\Core\HttpFoundation\Response $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     *
     * @return type the status of the response
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     *
     * status HTTP of the response
     *
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function addPlugins(AbstractSmartyPlugin $plugin)
    {
        $this->plugins[] = $plugin;
    }

    public function registerPlugins()
    {
        foreach ($this->plugins as $register_plugin) {
            $plugins = $register_plugin->getPluginDescriptors();

            if (!is_array($plugins)) {
                $plugins = array($plugins);
            }

            foreach ($plugins as $plugin) {
                $this->registerPlugin(
                    $plugin->getType(),
                    $plugin->getName(),
                    array(
                        $plugin->getClass(),
                        $plugin->getMethod()
                    )
                );
            }
        }
    }

}
