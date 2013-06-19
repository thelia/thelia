<?php

namespace Thelia\Core\Template;

use Symfony\Component\DependencyInjection\ContainerInterface;

use \Smarty;

use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\Loop\Category;

use Thelia\Core\Template\Smarty\SmartyPluginInterface;

/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SmartyParser extends Smarty implements ParserInterface {

    public $plugins = array();

    protected $container;

    protected $template = "smarty-sample";

    protected $status = 200;

    protected $loopDefinition = array();

    protected $asset_manager = null; // Lazy loading

    /**
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function __construct(ContainerInterface $container, $template = false)
    {
        parent::__construct();

        $this->container = $container;

        // Configure basic Smarty parameters

        $compile_dir = THELIA_ROOT . 'cache/smarty/compile';
        if (! is_dir($compile_dir)) @mkdir($compile_dir, 0777, true);

        $cache_dir = THELIA_ROOT . 'cache/smarty/cache';
        if (! is_dir($cache_dir)) @mkdir($cache_dir, 0777, true);

        if ($template != false) $this->template = $template;

        $this->setTemplateDir(THELIA_TEMPLATE_DIR.$this->template);

        $this->setCompileDir($compile_dir);
        $this->setCacheDir($cache_dir);

        // Prevent smarty ErrorException: Notice: Undefined index bla bla bla...
        $this->error_reporting = E_ALL ^ E_NOTICE;

        // The default HTTP status
        $this->status = 200;

        // Register Thelia base block plugins
        $this->registerPlugin('block', 'loop'     , array($this, 'theliaLoop'));
        $this->registerPlugin('block', 'elseloop' , array($this, 'theliaElseloop'));
        $this->registerPlugin('block', 'ifloop'   , array($this, 'theliaIfLoop'));

        // Register translation function 'intl'
        $this->registerPlugin('function', 'intl', array($this, 'theliaTranslate'));

        // Register Thelia modules inclusion function 'thelia_module'
        $this->registerPlugin('function', 'thelia_module', array($this, 'theliaModule'));
    }

    /**
     * Process {loop name="loop name" type="loop type" ... } ... {/loop} block
     *
     * @param unknown $params
     * @param unknown $content
     * @param unknown $template
     * @param unknown $repeat
     * @throws \InvalidArgumentException
     * @return string
     */
    public function theliaLoop($params, $content, $template, &$repeat) {

    	if (empty($params['name']))
    		throw new \InvalidArgumentException("Missing 'name' parameter in loop arguments");

    	if (empty($params['type']))
    		throw new \InvalidArgumentException("Missing 'type' parameter in loop arguments");

    	$name = $params['name'];

    	if ($content === null) {

    		$loop = $this->createLoopInstance(strtolower($params['type']));

    		$this->getLoopArgument($loop, $params);

    		$loopResults = $loop->exec();

    		$template->assignByRef($name, $loopResults);
    	}
    	else {

    		$loopResults = $template->getTemplateVars($name);

    		$loopResults->next();
    	}

    	if ($loopResults->valid()) {

    		$loopResultRow = $loopResults->current();

    		foreach($loopResultRow->getVarVal() as $var => $val) {

    			$template->assign(substr($var, 1), $val);

    			$template->assign('__COUNT__', 1 + $loopResults->key());
    			$template->assign('__TOTAL__', $loopResults->getCount());
    		}

    		$repeat = $loopResults->valid();
    	}

    	if ($content !== null) {

    		if ($loopResults->isEmpty()) $content = "";

    		return $content;
    	}
    }


    /**
     * Process {elseloop rel="loopname"} ... {/elseloop} block
     *
     * @param unknown $params
     * @param unknown $content
     * @param unknown $template
     * @param unknown $repeat
     * @return Ambigous <string, unknown>
     */
    public function theliaElseloop($params, $content, $template, &$repeat) {

    	// When encoutering close tag, check if loop has results.
    	if ($repeat === false) {
    		return $this->checkEmptyLoop($params, $template) ? $content : '';
    	}
    }


    /**
     * Process {ifloop rel="loopname"} ... {/ifloop} block
     *
     * @param unknown $params
     * @param unknown $content
     * @param unknown $template
     * @param unknown $repeat
     * @return Ambigous <string, unknown>
     */
    public function theliaIfLoop($params, $content, $template, &$repeat) {

    	// When encountering close tag, check if loop has results.
    	if ($repeat === false) {
    		return $this->checkEmptyLoop($params, $template) ? '' : $content;
    	}
    }

    /**
     * Process translate function
     *
     * @param unknown $params
     * @param unknown $smarty
     * @return string
     */
    public function theliaTranslate($params, &$smarty)
    {
    	if (isset($params['l'])) {
    		$string = str_replace('\'', '\\\'', $params['l']);
    	}
    	else {
    		$string = '';
    	}

    	// TODO

    	return "[$string]";
    }


    /**
     * Process theliaModule template inclusion function
     *
     * @param unknown $params
     * @param unknown $smarty
     * @return string
     */
    public function theliaModule($params, &$smarty)
    {
        // TODO
        return "";
    }

	/**
	 *
	 * This method must return a Symfony\Component\HttpFoudation\Response instance or the content of the response
	 *
	 */
	public function getContent()
	{
	    $this->registerPlugins();

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

	/**
	 * Check if a loop has returned results. The loop shoud have been executed before, or an
	 * InvalidArgumentException is thrown
	 *
	 * @param unknown $params
	 * @param unknown $template
	 * @throws \InvalidArgumentException
	 */
    protected function checkEmptyLoop($params, $template) {
        if (empty($params['rel']))
        	throw new \InvalidArgumentException("Missing 'rel' parameter in ifloop/elseloop arguments");

        $loopName = $params['rel'];

         // Find loop results in the current template vars
         $loopResults = $template->getTemplateVars($loopName);

         if (empty($loopResults)) {
             throw new \InvalidArgumentException("Loop $loopName is not defined.");
         }

         return $loopResults->isEmpty();
    }


    /**
     *
     * Injects an associative array containing information for loop execution
     *
     * key is loop name
     * value is the class implementing/extending base loop classes
     *
     * ex :
     *
     * $loop = array(
     *  "product" => "Thelia\Loop\Product",
     *  "category" => "Thelia\Loop\Category",
     *  "myLoop" => "My\Own\Loop"
     * );
     *
     * @param  array                     $loops
     * @throws \InvalidArgumentException if loop name already exists
     */
    public function setLoopList(array $loopDefinition)
    {
    	foreach ($loopDefinition as $name => $className) {
    		if (array_key_exists($name, $this->loopDefinition)) {
    			throw new \InvalidArgumentException(sprintf("%s loop name already exists for %s class name", $name, $className));
    		}

    		$this->loopDefinition[$name] = $className;
    	}
    }

    public function addPlugins(SmartyPluginInterface $plugin)
    {
    	$this->plugins[] = $plugin;
    }

    public function registerPlugins()
    {
    	foreach ($this->plugins as $register_plugin) {
    		$plugins = $register_plugin->registerPlugins();

    		if(!is_array($plugins)) {
    			$plugins = array($plugins);
    		}

    		foreach ($plugins as $plugin) {
    			$this->registerPlugin(
    					$plugin->type,
    					$plugin->name,
    					array(
    							$plugin->class,
    							$plugin->method
    					)
    			);
    		}
    	}
    }


    /**
     * Returns the value of a loop argument.
     *
     * @param unknown $loop a BaseLoop instance
     * @param unknown $smartyParam
     * @throws \InvalidArgumentException
     */
    protected function getLoopArgument($loop, $smartyParam)
    {
    	$defaultItemsParams = array('required' => true);

    	$shortcutItemParams = array('optional' => array('required' => false));

    	$errorCode = 0;
    	$faultActor = array();
    	$faultDetails = array();

    	foreach($loop->defineArgs() as $name => $param){
    		if(is_integer($name)){
    			$name = $param;
    			$param = $defaultItemsParams;
    		}

    		if(is_string($param) && array_key_exists($param, $shortcutItemParams)){
    			$param = $shortcutItemParams[$param];
    		}

    		if(!is_array($param)){
    			$param = array('default' => $param);
    		}

    		$value = isset($smartyParam[$name]) ? $smartyParam[$name] : null;

    		if($value == null){
    			if(isset($param['default'])){
    				$value = $param['default'];
    			}
    			else if($param['required'] === true){
    				$faultActor[] = $name;
    				$faultDetails[] = sprintf('"%s" parameter is missing', $name);
    				continue;
    			}
    		}

    		$loop->{$name} = $value;
    	}

    	if(!empty($faultActor)){

    		$complement = sprintf('[%s]', implode(', ', $faultDetails));
    		throw new \InvalidArgumentException($complement);
    	}
    }

    /**
     *
     * find the loop class with his name and construct an instance of this class
     *
     * @param  string $name
     * @return \Thelia\Tpex\Element\Loop\BaseLoop
     * @throws \Thelia\Tpex\Exception\InvalidElementException
     * @throws \Thelia\Tpex\Exception\ElementNotFoundException
     */
    protected function createLoopInstance($name)
    {

        if (! isset($this->loopDefinition[$name])) {
            throw new ElementNotFoundException(sprintf("%s loop does not exists", $name));
        }

        $class = new \ReflectionClass($this->loopDefinition[$name]);

        if ($class->isSubclassOf("Thelia\Tpex\Element\Loop\BaseLoop") === false) {
            throw new InvalidElementException(sprintf("%s Loop class have to extends Thelia\Tpex\Element\Loop\BaseLoop",
                $name));
        }

        return $class->newInstance(
            $this->container->get('request'),
            $this->container->get('event_dispatcher')
        );
    }

    protected function getTemplateFilePath()
    {
        $request = $this->container->get('request');

    	$file = $request->attributes->get('_view');

    	$fileName = THELIA_TEMPLATE_DIR . rtrim($this->template, "/") . "/" . $file . ".html";

    	if (file_exists($fileName)) return $fileName;

    	throw new ResourceNotFoundException(sprintf("%s file not found in %s template", $file, $this->template));
    }

    protected function getAssetManager() {

        if ($this->asset_manager == null)
            $this->asset_manager = new SmartyAssetsManager(THELIA_WEB_DIR, "assets/$this->template");

        return $this->asset_manager;
    }
}
