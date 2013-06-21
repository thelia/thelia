<?php

namespace Thelia\Core\Template\Smarty;

use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\EventDispatcher\EventDispatcherInterface;

use \Smarty;

use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\Loop\Category;

use Thelia\Core\Template\Smarty\SmartyPluginInterface;
use Thelia\Core\Template\Smarty\Assets\SmartyAssetsManager;
use Thelia\Core\Template\Exception\ResourceNotFoundException;

/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SmartyParser extends Smarty implements ParserInterface {

    public $plugins = array();

    protected $request, $dispatcher;

    protected $template = "";

    protected $status = 200;

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param bool $template
     * @param string $env Environment define for the kernel application. Used for the cache directory
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

        $this->setTemplate($template ?: 'smarty-sample');

        $this->setCompileDir($compile_dir);
        $this->setCacheDir($cache_dir);

        $this->debugging = $debug;

        // Prevent smarty ErrorException: Notice: Undefined index bla bla bla...
        $this->error_reporting = E_ALL ^ E_NOTICE;

        // Activer le cache, avec une lifetime de 15mn, et en vérifiant que les templates sources n'ont pas été modifiés.
        $this->caching        = 1;
        $this->cache_lifetime = 300;
        $this->compile_check  = true;

        // The default HTTP status
        $this->status = 200;
    }

    public function setTemplate($template_path_from_template_base) {

        $this->template = $template_path_from_template_base;

        $this->setTemplateDir(THELIA_TEMPLATE_DIR.$this->template);
    }

    public function getTemplate() {
    	return $this->template;
    }

    /**
     * Return a rendered template file
     *
     * @param string $realTemplateName the template name (from the template directory)
     * @param array $parameters an associative array of names / value pairs
     * @return string the rendered template text
     */
    public function render($realTemplateName, array $parameters) {

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

    		if(!is_array($plugins)) {
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