<?php

namespace Thelia\Core\Template;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Template\ParserInterface;
use \Smarty;
use Thelia\Core\Template\Loop\Category;

class SmartyParser extends Smarty implements ParserInterface {

    /**
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    protected $template = "smarty-sample";

    protected $status = 200;

    protected $loopDefinition = array();

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();

        $this->container = $container;

        $compile_dir = THELIA_ROOT . 'cache/smarty/compile';
        if (! is_dir($compile_dir)) @mkdir($compile_dir, 0777, true);

        $cache_dir = THELIA_ROOT . 'cache/smarty/cache';
        if (! is_dir($cache_dir)) @mkdir($cache_dir, 0777, true);

        $this->setTemplateDir(THELIA_TEMPLATE_DIR.$this->template);

        $this->setCompileDir($compile_dir);
        $this->setCacheDir($cache_dir);

        $this->registerPlugin('block', 'loop', array($this, 'theliaLoop'));
        $this->registerPlugin('block', 'empty', array($this, 'theliaEmpty'));
        $this->registerPlugin('block', 'notempty', array($this, 'theliaNotEmpty'));


        // Prevent ErrorException: Notice: Undefined index
        $this->error_reporting = E_ALL ^ E_NOTICE;

        $this->status = 200;
    }

    /**
     *
     * associative array containing information for loop execution
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

    private function extractParam($loop, $smartyParam)
    {
    	$defaultItemsParams = array('required' => true);
    	$shortcutItemParams = array(
    			'optional' => array('required' => false)
    	);

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

    public function theliaEmpty($params, $content, $template, &$repeat) {

    	// When encoutering close tag, check if loop has results.
    	if ($repeat === false) {
    		return $this->checkEmptyLoop($params, $template) ? $content : '';
    	}
    }

    public function theliaNotEmpty($params, $content, $template, &$repeat) {

    	// When encoutering close tag, check if loop has results.
    	if ($repeat === false) {
    		return $this->checkEmptyLoop($params, $template) ? '' : $content;
    	}
    }

    private function checkEmptyLoop($params, $template) {
        if (empty($params['name']))
        	throw new \InvalidArgumentException("Missing 'name' parameter in conditional loop arguments");

        $loopName = $params['name'];

         // Find loop results in the current template vars
         $loopResults = $template->getTemplateVars($loopName);

         if (empty($loopResults)) {
             throw new \InvalidArgumentException("Loop $loopName is not dfined.");
         }

         return $loopResults->isEmpty();
    }

    public function theliaLoop($params, $content, $template, &$repeat) {

        if (empty($params['name']))
            throw new \InvalidArgumentException("Missing 'name' parameter in loop arguments");

        if (empty($params['type']))
        	throw new \InvalidArgumentException("Missing 'type' parameter in loop arguments");

        $name = $params['name'];

        if ($content === null) {

            $loop = $this->createLoopInstance(strtolower($params['type']));

            $this->extractParam($loop, $params);

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

    /**
     * Main parser function, load the parser
     */
    public function loadParser()
    {
        $file = $this->getTemplateFilePath();

    	echo  "f=$file";
    }

    protected function getTemplateFilePath()
    {
        $request = $this->container->get('request');

    	$file = $request->attributes->get('_view');

    	$fileName = THELIA_TEMPLATE_DIR . rtrim($this->template, "/") . "/" . $file . ".html";

    	if (file_exists($fileName)) return $fileName;

    	throw new ResourceNotFoundException(sprintf("%s file not found in %s template", $file, $this->template));
    }

}
