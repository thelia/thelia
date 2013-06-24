<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

namespace Thelia\Core\Template\Smarty\Plugins;

use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Smarty\SmartyPluginInterface;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;

use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Thelia\Core\Template\Element\Exception\InvalidElementException;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TheliaLoop implements SmartyPluginInterface {

    protected $loopDefinition = array();

    protected $request;

    protected $dispatcher;

    public function __construct(Request $request, EventDispatcherInterface $dispatcher) {
        $this->request = $request;
        $this->dispatcher = $dispatcher;
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
     * find the loop class with his name and construct an instance of this class
     *
     * @param  string $name
     * @return \Thelia\Core\Template\Element\BaseLoop
     * @throws \Thelia\Tpex\Exception\InvalidElementException
     * @throws \Thelia\Tpex\Exception\ElementNotFoundException
     */
    protected function createLoopInstance($name)
    {

    	if (! isset($this->loopDefinition[$name])) {
    		throw new ElementNotFoundException(sprintf("%s loop does not exists", $name));
    	}

    	$class = new \ReflectionClass($this->loopDefinition[$name]);

    	if ($class->isSubclassOf("Thelia\Core\Template\Element\BaseLoop") === false) {
    		throw new InvalidElementException(sprintf("%s Loop class have to extends Thelia\Core\Template\Element\BaseLoop",
    				$name));
    	}

    	return $class->newInstance(
    			$this->request,
    			$this->dispatcher
    	);
    }


    /**
     * Returns the value of a loop argument.
     *
     * @param unknown $loop a BaseLoop instance
     * @param unknown $smartyParam
     * @throws \InvalidArgumentException
     */
    protected function getLoopArgument(BaseLoop $loop, $smartyParam)
    {
    	$defaultItemsParams = array('required' => true);

    	$shortcutItemParams = array('optional' => array('required' => false));

    	$errorCode = 0;
    	$faultActor = array();
    	$faultDetails = array();

        $argumentsCollection = $loop->defineArgs();
        $argumentsCollection->rewind();

        while ($argumentsCollection->valid()) {

            $argument = $argumentsCollection->current();
            $argumentsCollection->next();

            $value = isset($smartyParam[$argument->name]) ? $smartyParam[$argument->name] : null;

            /* check if mandatory */
            if($value === null && $argument->mandatory) {
                $faultActor[] = $argument->name;
                $faultDetails[] = sprintf('"%s" parameter is missing', $argument->name);
                continue;
            }

            /* check if empty */
            if($value === '' && !$argument->empty) {
                $faultActor[] = $argument->name;
                $faultDetails[] = sprintf('"%s" parameter cannot be empty', $argument->name);
                continue;
            }

            /* check default */
            if($value === null) {
                $value = $argument->default;
            }

            $loop->{$argument->name} = $value;
        }

    	if(!empty($faultActor)){

    		$complement = sprintf('[%s]', implode(', ', $faultDetails));
    		throw new \InvalidArgumentException($complement);
    	}
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

    /**
     * Defines the various smarty plugins hendled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
    		new SmartyPluginDescriptor('block', 'loop'     , $this, 'theliaLoop'),
    		new SmartyPluginDescriptor('block', 'elseloop' , $this, 'theliaElseloop'),
    		new SmartyPluginDescriptor('block', 'ifloop'   , $this, 'theliaIfLoop')
        );
     }
}