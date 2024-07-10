<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaSmarty\Template;

use Imagine\Exception\InvalidArgumentException;
use Smarty;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

/**
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class SmartyParser extends \Smarty implements ParserInterface
{
    public $plugins = [];

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var ParserContext */
    protected $parserContext;

    /** @var TemplateHelperInterface */
    protected $templateHelper;

    /** @var RequestStack */
    protected $requestStack;

    protected $backOfficeTemplateDirectories = [];
    protected $frontOfficeTemplateDirectories = [];

    protected $templateDirectories = [];

    /** @var TemplateDefinition */
    protected $templateDefinition;

    /** @var bool if true, resources will also be searched in the default template */
    protected $fallbackToDefaultTemplate = false;

    /** @var int */
    protected $status = 200;

    /** @var string */
    protected $env;

    /** @var bool */
    protected $debug;

    /** @var array The template stack */
    protected $tplStack = [];

    /** @var bool */
    protected $useMethodCallWrapper = false;

    /**
     * @param string $kernelEnvironment
     * @param bool   $kernelDebug
     *
     * @throws \SmartyException
     */
    public function __construct(
        RequestStack $requestStack,
        EventDispatcherInterface $dispatcher,
        ParserContext $parserContext,
        TemplateHelperInterface $templateHelper,
        $kernelEnvironment = 'prod',
        $kernelDebug = false
    ) {
        parent::__construct();

        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
        $this->parserContext = $parserContext;
        $this->templateHelper = $templateHelper;
        $this->env = $kernelEnvironment;
        $this->debug = $kernelDebug;

        // Use method call compatibility wrapper ?
        $this->useMethodCallWrapper = version_compare(self::SMARTY_VERSION, '3.1.33', '>=');

        // Configure basic Smarty parameters

        $compile_dir = THELIA_CACHE_DIR.DS.$kernelEnvironment.DS.'smarty'.DS.'compile';
        if (!is_dir($compile_dir)) {
            @mkdir($compile_dir, 0777, true);
        }

        $cache_dir = THELIA_CACHE_DIR.DS.$kernelEnvironment.DS.'smarty'.DS.'cache';
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0777, true);
        }

        $this->setCompileDir($compile_dir);
        $this->setCacheDir($cache_dir);

        // Prevent smarty ErrorException: Notice: Undefined index bla bla bla...
        $this->error_reporting = \E_ALL ^ \E_NOTICE;

        // The default HTTP status
        $this->status = 200;

        $this->registerFilter('output', [$this, 'trimWhitespaces']);
        $this->registerFilter('variable', [__CLASS__, 'theliaEscape']);
    }

    /**
     * Return the current request or null if no request exists.
     *
     * @return Request|null
     */
    public function getRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }

    /**
     * Trim whitespaces from the HTML output, preserving required ones in pre, textarea, javascript.
     * This methois uses 3 levels of trimming :.
     *
     *    - 0 : whitespaces are not trimmed, code remains as is.
     *    - 1 : only blank lines are trimmed, code remains indented and human-readable (the default)
     *    - 2 or more : all unnecessary whitespace are removed. Code is very hard to read.
     *
     * The trim level is defined by the configuration variable html_output_trim_level
     *
     * @param string $source the HTML source
     *
     * @return string
     */
    public function trimWhitespaces($source, /* @noinspection PhpUnusedParameterInspection */ \Smarty_Internal_Template $template)
    {
        $compressionMode = ConfigQuery::read('html_output_trim_level', 1);

        if ($compressionMode == 0) {
            return $source;
        }

        $store = [];
        $_store = 0;
        $_offset = 0;

        // Unify Line-Breaks to \n
        $source = preg_replace("/\015\012|\015|\012/", "\n", $source);

        // capture Internet Explorer Conditional Comments
        if ($compressionMode == 1) {
            $expressions = [
                // remove spaces between attributes (but not in attribute values!)
                '#(([a-z0-9]\s*=\s*(["\'])[^\3]*?\3)|<[a-z0-9_]+)\s+([a-z/>])#is' => '\1 \4',
                '/(^[\n]*|[\n]+)[\s\t]*[\n]+/' => "\n",
            ];
        } elseif ($compressionMode >= 2) {
            if (preg_match_all('#<!--\[[^\]]+\]>.*?<!\[[^\]]+\]-->#is', $source, $matches, \PREG_OFFSET_CAPTURE | \PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $store[] = $match[0][0];
                    $_length = \strlen($match[0][0]);
                    $replace = '@!@SMARTY:'.$_store.':SMARTY@!@';
                    $source = substr_replace($source, $replace, $match[0][1] - $_offset, $_length);

                    $_offset += $_length - \strlen($replace);
                    ++$_store;
                }
            }

            // Strip all HTML-Comments
            // yes, even the ones in <script> - see http://stackoverflow.com/a/808850/515124
            $source = preg_replace('#<!--.*?-->#ms', '', $source);

            $expressions = [
                // replace multiple spaces between tags by a single space
                // can't remove them entirely, becaue that might break poorly implemented CSS display:inline-block elements
                '#(:SMARTY@!@|>)\s+(?=@!@SMARTY:|<)#s' => '\1 \2',
                // remove spaces between attributes (but not in attribute values!)
                '#(([a-z0-9]\s*=\s*(["\'])[^\3]*?\3)|<[a-z0-9_]+)\s+([a-z/>])#is' => '\1 \4',
                // note: for some very weird reason trim() seems to remove spaces inside attributes.
                // maybe a \0 byte or something is interfering?
                '#^\s+<#Ss' => '<',
                '#>\s+$#Ss' => '>',
            ];
        } else {
            $expressions = [];
        }

        // capture html elements not to be messed with
        $_offset = 0;
        if (preg_match_all('#<(script|pre|textarea)[^>]*>.*?</\\1>#is', $source, $matches, \PREG_OFFSET_CAPTURE | \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $store[] = $match[0][0];
                $_length = \strlen($match[0][0]);
                $replace = '@!@SMARTY:'.$_store.':SMARTY@!@';
                $source = substr_replace($source, $replace, $match[0][1] - $_offset, $_length);

                $_offset += $_length - \strlen($replace);
                ++$_store;
            }
        }

        // Protect output against a potential regex execution error (e.g., PREG_BACKTRACK_LIMIT_ERROR)
        if (null !== $tmp = preg_replace(array_keys($expressions), array_values($expressions), $source)) {
            $source = $tmp;
            unset($tmp);
        } else {
            Tlog::getInstance()->error('Failed to trim whitespaces from parser output: '.preg_last_error());
        }

        // capture html elements not to be messed with
        $_offset = 0;
        if (preg_match_all('#@!@SMARTY:([0-9]+):SMARTY@!@#is', $source, $matches, \PREG_OFFSET_CAPTURE | \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $store[] = $match[0][0];
                $_length = \strlen($match[0][0]);
                $replace = array_shift($store);
                $source = substr_replace($source, $replace, $match[0][1] + $_offset, $_length);

                $_offset += \strlen($replace) - $_length;
                ++$_store;
            }
        }

        return $source;
    }

    /**
     * Add a template directory to the current template list.
     *
     * @param int    $templateType      the template type (a TemplateDefinition type constant)
     * @param string $templateName      the template name
     * @param string $templateDirectory path to the template directory
     * @param string $key               ???
     * @param bool   $addAtBeginning    if true, the template definition should be added at the beginning of the template directory list
     */
    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $addAtBeginning = false): void
    {
        Tlog::getInstance()->addDebug("Adding template directory $templateDirectory, type:$templateType name:$templateName, key: $key");

        if (true === $addAtBeginning && isset($this->templateDirectories[$templateType][$templateName])) {
            // When using array_merge, the key was set to 0. Use + instead.
            $this->templateDirectories[$templateType][$templateName] =
                [$key => $templateDirectory] + $this->templateDirectories[$templateType][$templateName]
            ;
        } else {
            $this->templateDirectories[$templateType][$templateName][$key] = $templateDirectory;
        }
    }

    /**
     * Return the registered template directories for a given template type.
     *
     * @param int $templateType
     *
     * @throws InvalidArgumentException
     *
     * @return mixed:
     */
    public function getTemplateDirectories($templateType)
    {
        if (!isset($this->templateDirectories[$templateType])) {
            throw new InvalidArgumentException('Failed to get template type %', $templateType);
        }

        return $this->templateDirectories[$templateType];
    }

    public static function theliaEscape($content, /* @noinspection PhpUnusedParameterInspection */ $smarty)
    {
        if (\is_scalar($content)) {
            return htmlspecialchars($content, \ENT_QUOTES, \Smarty::$_CHARSET);
        }

        return $content;
    }

    /**
     * Set a new template definition, and save the current one.
     *
     * @param bool $fallbackToDefaultTemplate if true, resources will be also searched in the "default" template
     */
    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        if (null !== $this->templateDefinition) {
            $this->tplStack[] = [$this->templateDefinition, $this->fallbackToDefaultTemplate];
        }

        $this->setTemplateDefinition($templateDefinition, $fallbackToDefaultTemplate);
    }

    /**
     * Restore the previous stored template definition, if one exists.
     */
    public function popTemplateDefinition(): void
    {
        if (\count($this->tplStack) > 0) {
            [$templateDefinition, $fallbackToDefaultTemplate] = array_pop($this->tplStack);

            $this->setTemplateDefinition($templateDefinition, $fallbackToDefaultTemplate);
        }
    }

    /**
     * Configure the parser to use the template defined by $templateDefinition.
     *
     * @param bool $fallbackToDefaultTemplate if true, resources will be also searched in the "default" template
     */
    public function setTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        $this->templateDefinition = $templateDefinition;

        $this->fallbackToDefaultTemplate = $fallbackToDefaultTemplate;

        // Clear the current Smarty template path list
        $this->setTemplateDir([]);

        // -------------------------------------------------------------------------------------------------------------
        // Add current template and its parent to the registered template list
        // using "*template-assets" keys.
        // -------------------------------------------------------------------------------------------------------------

        $templateList = ['' => $templateDefinition] + $templateDefinition->getParentList();

        /** @var TemplateDefinition $template */
        foreach (array_reverse($templateList) as $template) {
            // Add template directories  in the current template, in order to get assets
            $this->addTemplateDirectory(
                $templateDefinition->getType(),
                $template->getName(), // $templateDefinition->getName(), // We add the template definition in the main template directory
                $template->getAbsolutePath(),
                self::TEMPLATE_ASSETS_KEY, // $templateKey,
                true
            );
        }

        // -------------------------------------------------------------------------------------------------------------
        // Add template and its parent pathes to the Smarty template path list
        // using "*template-assets" keys.
        // -------------------------------------------------------------------------------------------------------------

        /**
         * @var string             $keyPrefix
         * @var TemplateDefinition $template
         */
        foreach ($templateList as $keyPrefix => $template) {
            $templateKey = $keyPrefix.self::TEMPLATE_ASSETS_KEY;

            // Add the template directory to the Smarty search path
            $this->addTemplateDir($template->getAbsolutePath(), $templateKey);

            // Also add the configuration directory
            $this->addConfigDir(
                $template->getAbsolutePath().DS.'configs',
                $templateKey
            );
        }

        // -------------------------------------------------------------------------------------------------------------
        // Add all modules template directories foreach of the template list to the Smarty search path.
        // -------------------------------------------------------------------------------------------------------------

        $type = $templateDefinition->getType();

        foreach ($templateList as $keyPrefix => $template) {
            if (isset($this->templateDirectories[$type][$template->getName()])) {
                foreach ($this->templateDirectories[$type][$template->getName()] as $key => $directory) {
                    if (null === $this->getTemplateDir($key)) {
                        $this->addTemplateDir($directory, $key);
                        $this->addConfigDir($directory.DS.'configs', $key);
                    }
                }
            }
        }

        // -------------------------------------------------------------------------------------------------------------
        // Add the "default" modules template directories if we have to fallback to "default"
        // -------------------------------------------------------------------------------------------------------------

        if ($fallbackToDefaultTemplate) {
            if (isset($this->templateDirectories[$type]['default'])) {
                foreach ($this->templateDirectories[$type]['default'] as $key => $directory) {
                    if (null === $this->getTemplateDir($key)) {
                        $this->addTemplateDir($directory, $key);
                        $this->addConfigDir($directory.DS.'configs', $key);
                    }
                }
            }
        }
    }

    /**
     * Get template definition.
     *
     * @param bool|string $webAssetTemplateName false to use the current template path, or a template name to
     *                                          load assets from this template instead of the current one
     *
     * @return TemplateDefinition
     */
    public function getTemplateDefinition($webAssetTemplateName = false)
    {
        // Deep clone of template definition. We could change the template descriptor of template definition,
        // and we don't want to change the current template definition.

        /** @var TemplateDefinition $ret */
        $ret = unserialize(serialize($this->templateDefinition));

        if (false !== $webAssetTemplateName) {
            $customPath = str_replace($ret->getName(), $webAssetTemplateName, $ret->getPath());
            $ret->setName($webAssetTemplateName);
            $ret->setPath($customPath);
        }

        return $ret;
    }

    /**
     * Check if template definition is not null.
     *
     * @return bool
     */
    public function hasTemplateDefinition()
    {
        return $this->templateDefinition !== null;
    }

    /**
     * Get the current status of the fallback to "default" feature.
     *
     * @return bool
     */
    public function getFallbackToDefaultTemplate()
    {
        return $this->fallbackToDefaultTemplate;
    }

    /**
     * @return string the template path
     */
    public function getTemplatePath()
    {
        return $this->templateDefinition->getPath();
    }

    /**
     * Return a rendered template, either from file or from a string.
     *
     * @param string $resourceType    either 'string' (rendering from a string) or 'file' (rendering a file)
     * @param string $resourceContent the resource content (a text, or a template file name)
     * @param array  $parameters      an associative array of names / value pairs
     * @param bool   $compressOutput  if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     *
     * @throws \Exception
     * @throws \SmartyException
     *
     * @return string the rendered template text
     */
    protected function internalRenderer($resourceType, $resourceContent, array $parameters, $compressOutput = true)
    {
        // If we have to diable the output compression, just unregister the output filter temporarly
        if ($compressOutput == false) {
            $this->unregisterFilter('output', [$this, 'trimWhitespaces']);
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
                'debug' => $this->debug,
            ],
        ]);

        // Assign the parserContext variables
        foreach ($this->parserContext as $var => $value) {
            $this->assign($var, $value);
        }

        $this->assign($parameters);

        if (ConfigQuery::read('smarty_mute_undefined_or_null', 0)) {
            $this->muteUndefinedOrNullWarnings();
        }

        $output = $this->fetch($resourceType.':'.$resourceContent);

        if (!$compressOutput) {
            $this->registerFilter('output', [$this, 'trimWhitespaces']);
        }

        return $output;
    }

    /**
     * Return a rendered template file.
     *
     * @param string $realTemplateName the template name (from the template directory)
     * @param array  $parameters       an associative array of names / value pairs
     * @param bool   $compressOutput   if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     *
     * @throws ResourceNotFoundException if the template cannot be found
     * @throws \Exception
     * @throws \SmartyException
     *
     * @return string the rendered template text
     */
    public function render($realTemplateName, array $parameters = [], $compressOutput = true)
    {
        if (false === $this->templateExists($realTemplateName) || false === $this->checkTemplate($realTemplateName)) {
            throw new ResourceNotFoundException(Translator::getInstance()->trans('Template file %file cannot be found.', ['%file' => $realTemplateName]));
        }

        return $this->internalRenderer('file', $realTemplateName, $parameters, $compressOutput);
    }

    private function checkTemplate($fileName)
    {
        $templates = $this->getTemplateDir();

        $found = true;

        /* @noinspection PhpUnusedLocalVariableInspection */
        foreach ($templates as $key => $value) {
            $absolutePath = rtrim(realpath(\dirname($value.$fileName)), '/');
            $templateDir = rtrim(realpath($value), '/');
            if (!empty($absolutePath) && !str_starts_with($absolutePath, $templateDir)) {
                $found = false;
            }
        }

        return $found;
    }

    /**
     * Return a rendered template text.
     *
     * @param string $templateText   the template text
     * @param array  $parameters     an associative array of names / value pairs
     * @param bool   $compressOutput if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     *
     * @throws \Exception
     * @throws \SmartyException
     *
     * @return string the rendered template text
     */
    public function renderString($templateText, array $parameters = [], $compressOutput = true)
    {
        return $this->internalRenderer('string', $templateText, $parameters, $compressOutput);
    }

    /**
     * @return int the status of the response
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * status HTTP of the response.
     *
     * @param int $status
     */
    public function setStatus($status): void
    {
        $this->status = $status;
    }

    public function addPlugins(AbstractSmartyPlugin $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    /**
     * From Smarty 3.1.33, we cannot pass parameters by reference to plugin mehods, and declarations like the
     * following will throw the error "Warning: Parameter 2 to <method> expected to be a reference, value given",
     * because Smarty uses call_user_func_array() to call plugins methods.
     *
     *     public function categoryDataAccess($params, &$smarty)
     *
     * We use now a wrapper to provide compatibility with this declaration style
     *
     * @see AbstractSmartyPlugin::__call() for details
     *
     * @throws \SmartyException
     */
    public function registerPlugins(): void
    {
        /** @var AbstractSmartyPlugin $register_plugin */
        foreach ($this->plugins as $register_plugin) {
            $plugins = $register_plugin->getPluginDescriptors();

            if (!\is_array($plugins)) {
                $plugins = [$plugins];
            }

            /** @var SmartyPluginDescriptor $plugin */
            foreach ($plugins as $plugin) {
                // Use the wrapper to ensure Smarty 3.1.33 compatibility
                $methodName = $this->useMethodCallWrapper && $plugin->getType() === 'function' ?
                    AbstractSmartyPlugin::WRAPPED_METHOD_PREFIX.$plugin->getMethod() :
                    $plugin->getMethod()
                ;

                $this->registerPlugin(
                    $plugin->getType(),
                    $plugin->getName(),
                    [
                        $plugin->getClass(),
                        $methodName,
                    ]
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
