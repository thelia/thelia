<?php

namespace Thelia\Core\Template\Smarty;

use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\EventDispatcher\EventDispatcherInterface;

use \Smarty;

use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;

use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ConfigQuery;
use Thelia\Core\Template\TemplateHelper;

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

    protected $template = "";

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

        if($debug) {
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

    public function addBackOfficeTemplateDirectory($templateName, $templateDirectory, $key)
    {
        $this->backOfficeTemplateDirectories[$templateName][$key] = $templateDirectory;
    }

    public function addFrontOfficeTemplateDirectory($templateName, $templateDirectory, $key)
    {
        $this->frontOfficeTemplateDirectories[$templateName][$key] = $templateDirectory;
    }

    /**
     * @param TemplateDefinition $templateDefinition
     */
    public function setTemplate(TemplateDefinition $templateDefinition)
    {
        $this->template = $templateDefinition->getPath();

        /* init template directories */
        $this->setTemplateDir(array());

        /* add main template directory */
        $this->addTemplateDir(THELIA_TEMPLATE_DIR . $this->template, 0);

        /* define config directory */
        $configDirectory = THELIA_TEMPLATE_DIR . $this->template . '/configs';
        $this->setConfigDir($configDirectory);

        /* add modules template directories */
        switch($templateDefinition->getType()) {
            case TemplateDefinition::FRONT_OFFICE:
                /* do not pass array directly to addTemplateDir since we cant control on keys */
                if(isset($this->frontOfficeTemplateDirectories[$templateDefinition->getName()])) {
                    foreach($this->frontOfficeTemplateDirectories[$templateDefinition->getName()] as $key => $directory) {
                        $this->addTemplateDir($directory, $key);
                    }
                }
                break;

            case TemplateDefinition::BACK_OFFICE:
                /* do not pass array directly to addTemplateDir since we cant control on keys */
                if(isset($this->backOfficeTemplateDirectories[$templateDefinition->getName()])) {
                    foreach($this->backOfficeTemplateDirectories[$templateDefinition->getName()] as $key => $directory) {
                        $this->addTemplateDir($directory, $key);
                    }
                }
                break;

            case TemplateDefinition::PDF:
                break;

            default:
                break;
        }
    }

    public function getTemplate()
    {
        return $this->template;
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
        if(false === $this->templateExists($realTemplateName)) {
            throw new ResourceNotFoundException();
        }
        // Assign the parserContext variables
        foreach ($this->parserContext as $var => $value) {
            $this->assign($var, $value);
        }

        $this->assign($parameters);

        return $this->fetch(sprintf("file:%s", $realTemplateName));
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
