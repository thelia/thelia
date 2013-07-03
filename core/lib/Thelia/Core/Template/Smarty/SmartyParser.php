<?php

namespace Thelia\Core\Template\Smarty;

use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\EventDispatcher\EventDispatcherInterface;

use \Smarty;

use Thelia\Core\Template\ParserInterface;

use Thelia\Core\Template\Smarty\SmartyPluginInterface;
use Thelia\Core\Template\Exception\ResourceNotFoundException;

/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class SmartyParser extends Smarty implements ParserInterface
{

    public $plugins = array();

    protected $request, $dispatcher;

    protected $template = "";

    protected $status = 200;

    /**
     * @param \Symfony\Component\HttpFoundation\Request                   $request
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param bool                                                        $template
     * @param string                                                      $env        Environment define for the kernel application. Used for the cache directory
     */
    public function __construct(Request $request, EventDispatcherInterface $dispatcher, $template = false, $env = "prod", $debug = false)
    {
        parent::__construct();

        $this->request = $request;
        $this->dispatcher = $dispatcher;

        // Configure basic Smarty parameters

        $compile_dir = THELIA_ROOT . 'cache/'. $env .'/smarty/compile';
        if (! is_dir($compile_dir)) @mkdir($compile_dir, 0777, true);

        $cache_dir = THELIA_ROOT . 'cache/'. $env .'/smarty/cache';
        if (! is_dir($cache_dir)) @mkdir($cache_dir, 0777, true);

        $this->setTemplate($template ?: 'smarty-sample'); // FIXME: put this in configuration

        $this->setCompileDir($compile_dir);
        $this->setCacheDir($cache_dir);

        $this->debugging = $debug;

        // Prevent smarty ErrorException: Notice: Undefined index bla bla bla...
        $this->error_reporting = E_ALL ^ E_NOTICE;

        // Si on n'est pas en mode debug, activer le cache, avec une lifetime de 15mn, et en vérifiant que les templates sources n'ont pas été modifiés.
        if($debug === false) {
            $this->caching        = Smarty::CACHING_LIFETIME_CURRENT;
            $this->cache_lifetime = 300;
            $this->compile_check  = true;
        } else {
            $this->caching       = Smarty::CACHING_OFF;
            $this->force_compile = true;
        }

        // The default HTTP status
        $this->status = 200;

        $this->registerFilter('pre', array($this, "preThelia"));
    }

    public function preThelia($tpl_source, \Smarty_Internal_Template $template)
    {
        $new_source = preg_replace('`{#([a-zA-Z][a-zA-Z0-9\-_]*)(.*)}`', '{\$$1$2}', $tpl_source);
        $new_source = preg_replace('`#([a-zA-Z][a-zA-Z0-9\-_]*)`', '{\$$1|default:\'#$1\'}', $new_source);

        return $new_source;
    }

    public function setTemplate($template_path_from_template_base)
    {
        $this->template = $template_path_from_template_base;

        $this->setTemplateDir(THELIA_TEMPLATE_DIR.$this->template);
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
    public function render($realTemplateName, array $parameters)
    {
        $this->assign($parameters);

        return $this->fetch($realTemplateName);
    }

    /**
     *
     * This method must return a Symfony\Component\HttpFoudation\Response instance or the content of the response
     *
     */
    public function getContent()
    {
        return $this->fetch($this->getTemplateFilePath());
    }

    /**
     *
     * set $content with the body of the response or the Response object directly
     *
     * @param string|Symfony\Component\HttpFoundation\Response $content
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

    public function addPlugins(SmartyPluginInterface $plugin)
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

    protected function getTemplateFilePath()
    {
         $file = $this->request->attributes->get('_view');

        $fileName = THELIA_TEMPLATE_DIR . rtrim($this->template, "/") . "/" . $file . ".html";

        if (file_exists($fileName)) return $fileName;

        throw new ResourceNotFoundException(sprintf("%s file not found in %s template", $file, $this->template));
    }
}
