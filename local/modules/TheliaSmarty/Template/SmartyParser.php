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

namespace TheliaSmarty\Template;

use \Smarty;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Template\TemplateDefinition;
use Imagine\Exception\InvalidArgumentException;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class SmartyParser extends Smarty implements ParserInterface
{
    public $plugins = array();

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var ParserContext */
    protected $parserContext;

    /** @var TemplateHelperInterface */
    protected $templateHelper;

    /** @var RequestStack */
    protected $requestStack;

    protected $backOfficeTemplateDirectories = array();
    protected $frontOfficeTemplateDirectories = array();

    protected $templateDirectories = array();

    /** @var TemplateDefinition */
    protected $templateDefinition;

    /** @var int */
    protected $status = 200;

    /** @var string */
    protected $env;

    /** @var bool */
    protected $debug;

    /**
     * @param RequestStack             $requestStack
     * @param EventDispatcherInterface $dispatcher
     * @param ParserContext            $parserContext
     * @param TemplateHelperInterface  $templateHelper
     * @param string                   $env
     * @param bool                     $debug
     */
    public function __construct(
        RequestStack $requestStack,
        EventDispatcherInterface $dispatcher,
        ParserContext $parserContext,
        TemplateHelperInterface $templateHelper,
        $env = "prod",
        $debug = false
    ) {
        parent::__construct();

        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
        $this->parserContext = $parserContext;
        $this->templateHelper = $templateHelper;
        $this->env = $env;
        $this->debug = $debug;

        // Configure basic Smarty parameters

        $compile_dir = THELIA_ROOT . 'cache'. DS . $env . DS . 'smarty' . DS . 'compile';
        if (! is_dir($compile_dir)) {
            @mkdir($compile_dir, 0777, true);
        }

        $cache_dir = THELIA_ROOT . 'cache'. DS . $env . DS . 'smarty' . DS . 'cache';
        if (! is_dir($cache_dir)) {
            @mkdir($cache_dir, 0777, true);
        }

        $this->setCompileDir($compile_dir);
        $this->setCacheDir($cache_dir);
        $this->inheritance_merge_compiled_includes = false;

        // Prevent smarty ErrorException: Notice: Undefined index bla bla bla...
        $this->error_reporting = E_ALL ^ E_NOTICE;

        // The default HTTP status
        $this->status = 200;

        $this->registerFilter('output', array($this, "trimWhitespaces"));
        $this->registerFilter('variable', array(__CLASS__, "theliaEscape"));
    }

    /**
     * Return the current request or null if no request exists
     *
     * @return Request|null
     */
    public function getRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }

    /**
     * Trim whitespaces from the HTML output, preserving required ones in pre, textarea, javascript.
     * This methois uses 3 levels of trimming :
     *
     *    - 0 : whitespaces are not trimmed, code remains as is.
     *    - 1 : only blank lines are trimmed, code remains indented and human-readable (the default)
     *    - 2 or more : all unnecessary whitespace are removed. Code is very hard to read.
     *
     * The trim level is defined by the configuration variable html_output_trim_level
     *
     * @param  string                    $source   the HTML source
     * @param  \Smarty_Internal_Template $template
     * @return string
     */
    public function trimWhitespaces($source, /** @noinspection PhpUnusedParameterInspection */ \Smarty_Internal_Template $template)
    {
        $compressionMode = ConfigQuery::read('html_output_trim_level', 1);

        if ($compressionMode == 0) {
            return $source;
        }

        $store = array();
        $_store = 0;
        $_offset = 0;

        // Unify Line-Breaks to \n
        $source = preg_replace("/\015\012|\015|\012/", "\n", $source);

        // capture Internet Explorer Conditional Comments
        if ($compressionMode == 1) {
            $expressions = array(
                // remove spaces between attributes (but not in attribute values!)
                '#(([a-z0-9]\s*=\s*(["\'])[^\3]*?\3)|<[a-z0-9_]+)\s+([a-z/>])#is' => '\1 \4',
                '/(^[\n]*|[\n]+)[\s\t]*[\n]+/' => "\n"
            );
        } elseif ($compressionMode >= 2) {
            if (preg_match_all('#<!--\[[^\]]+\]>.*?<!\[[^\]]+\]-->#is', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $store[] = $match[0][0];
                    $_length = strlen($match[0][0]);
                    $replace = '@!@SMARTY:' . $_store . ':SMARTY@!@';
                    $source = substr_replace($source, $replace, $match[0][1] - $_offset, $_length);

                    $_offset += $_length - strlen($replace);
                    $_store++;
                }
            }

            // Strip all HTML-Comments
            // yes, even the ones in <script> - see http://stackoverflow.com/a/808850/515124
            $source = preg_replace('#<!--.*?-->#ms', '', $source);

            $expressions = array(
                // replace multiple spaces between tags by a single space
                // can't remove them entirely, becaue that might break poorly implemented CSS display:inline-block elements
                '#(:SMARTY@!@|>)\s+(?=@!@SMARTY:|<)#s' => '\1 \2',
                // remove spaces between attributes (but not in attribute values!)
                '#(([a-z0-9]\s*=\s*(["\'])[^\3]*?\3)|<[a-z0-9_]+)\s+([a-z/>])#is' => '\1 \4',
                // note: for some very weird reason trim() seems to remove spaces inside attributes.
                // maybe a \0 byte or something is interfering?
                '#^\s+<#Ss' => '<',
                '#>\s+$#Ss' => '>',

            );
        } else {
            $expressions = array();
        }

        // capture html elements not to be messed with
        $_offset = 0;
        if (preg_match_all('#<(script|pre|textarea)[^>]*>.*?</\\1>#is', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $store[] = $match[0][0];
                $_length = strlen($match[0][0]);
                $replace = '@!@SMARTY:' . $_store . ':SMARTY@!@';
                $source = substr_replace($source, $replace, $match[0][1] - $_offset, $_length);

                $_offset += $_length - strlen($replace);
                $_store++;
            }
        }

        $source = preg_replace(array_keys($expressions), array_values($expressions), $source);

        // capture html elements not to be messed with
        $_offset = 0;
        if (preg_match_all('#@!@SMARTY:([0-9]+):SMARTY@!@#is', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $store[] = $match[0][0];
                $_length = strlen($match[0][0]);
                $replace = array_shift($store);
                $source = substr_replace($source, $replace, $match[0][1] + $_offset, $_length);

                $_offset += strlen($replace) - $_length;
                $_store++;
            }
        }

        return $source;
    }

    /**
     * Add a template directory to the current template list
     *
     * @param int     $templateType      the template type (a TemplateDefinition type constant)
     * @param string  $templateName      the template name
     * @param string  $templateDirectory path to the template directory
     * @param string  $key               ???
     * @param boolean $addAtBeginning    if true, the template definition should be added at the beginning of the template directory list
     */
    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $addAtBeginning = false)
    {
        Tlog::getInstance()->addDebug("Adding template directory $templateDirectory, type:$templateType name:$templateName, key: $key");

        if (true === $addAtBeginning && isset($this->templateDirectories[$templateType][$templateName])) {
            // When using array_merge, the key was set to 0. Use + instead.
            $this->templateDirectories[$templateType][$templateName] =
                [ $key => $templateDirectory ] + $this->templateDirectories[$templateType][$templateName]
            ;
        } else {
            $this->templateDirectories[$templateType][$templateName][$key] = $templateDirectory;
        }
    }

    /**
     * Return the registered template directories for a given template type
     *
     * @param  int                      $templateType
     * @throws InvalidArgumentException
     * @return mixed:
     */
    public function getTemplateDirectories($templateType)
    {
        if (! isset($this->templateDirectories[$templateType])) {
            throw new InvalidArgumentException("Failed to get template type %", $templateType);
        }

        return $this->templateDirectories[$templateType];
    }

    public static function theliaEscape($content, /** @noinspection PhpUnusedParameterInspection */ $smarty)
    {
        if (is_scalar($content)) {
            return htmlspecialchars($content, ENT_QUOTES, Smarty::$_CHARSET);
        } else {
            return $content;
        }
    }

    /**
     * @param TemplateDefinition $templateDefinition
     * @param bool $useFallback
     */
    public function setTemplateDefinition(TemplateDefinition $templateDefinition, $useFallback = false)
    {
        $this->templateDefinition = $templateDefinition;

        /* init template directories */
        $this->setTemplateDir(array());

        /* define config directory */
        $configDirectory = THELIA_TEMPLATE_DIR . $this->getTemplatePath() . DS . 'configs';
        $this->addConfigDir($configDirectory, self::TEMPLATE_ASSETS_KEY);

        /* add modules template directories */
        $this->addTemplateDirectory(
            $templateDefinition->getType(),
            $templateDefinition->getName(),
            THELIA_TEMPLATE_DIR . $this->getTemplatePath(),
            self::TEMPLATE_ASSETS_KEY,
            true
        );

        $type = $templateDefinition->getType();
        $name = $templateDefinition->getName();

        /* do not pass array directly to addTemplateDir since we cant control on keys */
        if (isset($this->templateDirectories[$type][$name])) {
            foreach ($this->templateDirectories[$type][$name] as $key => $directory) {
                $this->addTemplateDir($directory, $key);
                $this->addConfigDir($directory . DS . 'configs', $key);
            }
        }

        // fallback on default template
        if ($useFallback && 'default' !== $name) {
            if (isset($this->templateDirectories[$type]['default'])) {
                foreach ($this->templateDirectories[$type]['default'] as $key => $directory) {
                    if (null === $this->getTemplateDir($key)) {
                        $this->addTemplateDir($directory, $key);
                        $this->addConfigDir($directory . DS . 'configs', $key);
                    }
                }
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
        $ret = clone $this->templateDefinition;

        if (false !== $webAssetTemplate) {
            $customPath = str_replace($ret->getName(), $webAssetTemplate, $ret->getPath());
            $ret->setName($webAssetTemplate);
            $ret->setPath($customPath);
        }

        return $ret;
    }

    /**
     * @return string the template path
     */
    public function getTemplatePath()
    {
        return $this->templateDefinition->getPath();
    }

    /**
     * Return a rendered template, either from file or from a string
     *
     * @param string $resourceType    either 'string' (rendering from a string) or 'file' (rendering a file)
     * @param string $resourceContent the resource content (a text, or a template file name)
     * @param array  $parameters      an associative array of names / value pairs
     * @param bool   $compressOutput  if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     *
     * @return string the rendered template text
     */
    protected function internalRenderer($resourceType, $resourceContent, array $parameters, $compressOutput = true)
    {
        // If we have to diable the output compression, just unregister the output filter temporarly
        if ($compressOutput == false) {
            $this->unregisterFilter('output', array($this, "trimWhitespaces"));
        }

        // Assign the parserContext variables
        foreach ($this->parserContext as $var => $value) {
            $this->assign($var, $value);
        }

        $this->assign($parameters);

        $output = $this->fetch(sprintf("%s:%s", $resourceType, $resourceContent));

        if ($compressOutput == false) {
            $this->registerFilter('output', array($this, "trimWhitespaces"));
        }

        return $output;
    }

    /**
     * Return a rendered template file
     *
     * @param  string                    $realTemplateName the template name (from the template directory)
     * @param  array                     $parameters       an associative array of names / value pairs
     * @return string                    the rendered template text
     * @param  bool                      $compressOutput   if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     * @throws ResourceNotFoundException if the template cannot be found
     */
    public function render($realTemplateName, array $parameters = array(), $compressOutput = true)
    {
        if (false === $this->templateExists($realTemplateName) || false === $this->checkTemplate($realTemplateName)) {
            throw new ResourceNotFoundException(Translator::getInstance()->trans("Template file %file cannot be found.", array('%file' => $realTemplateName)));
        }

        // Prepare common template variables
        /** @var Session $session */
        $session = $this->getRequest()->getSession();

        $lang = $session ? $session->getLang() : Lang::getDefaultLanguage();

        $parameters = array_merge($parameters, [
            'locale' => $lang->getLocale(),
            'lang_code' => $lang->getCode(),
            'lang_id' => $lang->getId(),
            'current_url' => $this->getRequest()->getUri(),
            'app' => (object) [
                'environment' => $this->env,
                'request' => $this->getRequest(),
                'session' => $session,
                'debug' => $this->debug
            ]
        ]);

        return $this->internalRenderer('file', $realTemplateName, $parameters, $compressOutput);
    }

    private function checkTemplate($fileName)
    {
        $templates = $this->getTemplateDir();

        $found = true;

        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($templates as $key => $value) {
            $absolutePath = rtrim(realpath(dirname($value.$fileName)), "/");
            $templateDir =  rtrim(realpath($value), "/");
            if (!empty($absolutePath) && strpos($absolutePath, $templateDir) !== 0) {
                $found = false;
            }
        }

        return $found;
    }

    /**
     * Return a rendered template text
     *
     * @param  string $templateText   the template text
     * @param  array  $parameters     an associative array of names / value pairs
     * @param  bool   $compressOutput if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     * @return string the rendered template text
     */
    public function renderString($templateText, array $parameters = array(), $compressOutput = true)
    {
        return $this->internalRenderer('string', $templateText, $parameters, $compressOutput);
    }

    /**
     *
     * @return int the status of the response
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
        /** @var  AbstractSmartyPlugin $register_plugin */
        foreach ($this->plugins as $register_plugin) {
            $plugins = $register_plugin->getPluginDescriptors();

            if (!is_array($plugins)) {
                $plugins = array($plugins);
            }

            /** @var SmartyPluginDescriptor $plugin */
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

    /**
     * @return \Thelia\Core\Template\TemplateHelperInterface the parser template helper instance
     */
    public function getTemplateHelper()
    {
        return $this->templateHelper;
    }
}
