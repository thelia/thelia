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

class TheliaLoop implements SmartyPluginInterface
{
    protected static $pagination = null;

    protected $loopDefinition = array();

    protected $request;

    protected $dispatcher;

    protected $loopstack = array();
    protected $varstack = array();

    public function __construct(Request $request, EventDispatcherInterface $dispatcher)
    {
        $this->request = $request;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param $loopId
     *
     * @return \PropelModelPager
     */
    public static function getPagination($loopId)
    {
        if(!empty(self::$pagination[$loopId])) {
            return self::$pagination[$loopId];
        } else {
            return null;
        }
    }

    /**
     * Process {loop name="loop name" type="loop type" ... } ... {/loop} block
     *
     * @param  unknown                   $params
     * @param  unknown                   $content
     * @param  unknown                   $template
     * @param  unknown                   $repeat
     * @throws \InvalidArgumentException
     * @return string
     */
    public function theliaLoop($params, $content, $template, &$repeat)
    {
        if (empty($params['name']))
            throw new \InvalidArgumentException("Missing 'name' parameter in loop arguments");

        if (empty($params['type']))
            throw new \InvalidArgumentException("Missing 'type' parameter in loop arguments");

        $name = $params['name'];

        if ($content === null) {
            // Check if a loop with the same name exists in the current scope, and abort if it's the case.
            if (array_key_exists($name, $this->varstack)) {
            	throw new \InvalidArgumentException("A loop named '$name' already exists in the current scope.");
            }

            $loop = $this->createLoopInstance(strtolower($params['type']));

            $this->getLoopArgument($loop, $params);

            self::$pagination[$name] = null;

    		$loopResults = $loop->exec(self::$pagination[$name]);
            $this->loopstack[$name] = $loopResults;

        } else {
            $loopResults = $this->loopstack[$name];

            $loopResults->next();
        }

        if ($loopResults->valid()) {
            $loopResultRow = $loopResults->current();

            // On first iteration, save variables that may be overwritten by this loop
            if (! isset($this->varstack[$name])) {

                $saved_vars = array();

                $varlist = $loopResultRow->getVars();
                $varlist[] = 'LOOP_COUNT';
                $varlist[] = 'LOOP_TOTAL';

                foreach($varlist as $var) {
                    $saved_vars[$var] = $template->getTemplateVars($var);
                }

                $this->varstack[$name] = $saved_vars;
            }

            foreach($loopResultRow->getVarVal() as $var => $val) {
    			$template->assign($var, $val);
    		}

    		$repeat = true;
    	}

        // Assign meta information
        $template->assign('LOOP_COUNT', 1 + $loopResults->key());
        $template->assign('LOOP_TOTAL', $loopResults->getCount());

    	// Loop is terminated. Cleanup.
    	if (! $repeat) {
    	    // Restore previous variables values before terminating
    	    if (isset($this->varstack[$name])) {
    		    foreach($this->varstack[$name] as $var => $value) {
    			    $template->assign($var, $value);
    		    }

    		    unset($this->varstack[$name]);
    	    }
    	}

        if ($content !== null) {
            if ($loopResults->isEmpty()) {
                $content = "";
            }

            return $content;
        }
    }

    /**
     * Process {elseloop rel="loopname"} ... {/elseloop} block
     *
     * @param  unknown  $params
     * @param  unknown  $content
     * @param  unknown  $template
     * @param  unknown  $repeat
     * @return Ambigous <string, unknown>
     */
    public function theliaElseloop($params, $content, $template, &$repeat)
    {

    	// When encoutering close tag, check if loop has results.
    	if ($repeat === false) {
    		return $this->checkEmptyLoop($params, $template) ? $content : '';
    	}
    }

    /**
     * Process {ifloop rel="loopname"} ... {/ifloop} block
     *
     * @param  unknown  $params
     * @param  unknown  $content
     * @param  unknown  $template
     * @param  unknown  $repeat
     * @return Ambigous <string, unknown>
     */
    public function theliaIfLoop($params, $content, $template, &$repeat)
    {
    	// When encountering close tag, check if loop has results.
    	if ($repeat === false) {
    		return $this->checkEmptyLoop($params, $template) ? '' : $content;
    	}
    }

    /**
     * Process {pageloop rel="loopname"} ... {/pageloop} block
     *
     * @param $params
     * @param $content
     * @param $template
     * @param $repeat
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function theliaPageLoop($params, $content, $template, &$repeat)
    {
        if (empty($params['rel']))
            throw new \InvalidArgumentException("Missing 'rel' parameter in page loop");

        $loopName = $params['rel'];

        // Find loop results in the current template vars
        /* $loopResults = $template->getTemplateVars($loopName);
        if (empty($loopResults)) {
            throw new \InvalidArgumentException("Loop $loopName is not defined.");
        }*/

        // Find pagination
        $pagination = self::getPagination($loopName);
        if ($pagination === null) {
            throw new \InvalidArgumentException("Loop $loopName  is not defined");
        }

        if($pagination->getNbResults() == 0) {
            return '';
        }

        if ($content === null) {
            $page = 1;
        } else {
            $page = $template->getTemplateVars('PAGE');
            $page++;
        }

        if ($page <= $pagination->getLastPage()) {
            $template->assign('PAGE', $page);
            $template->assign('CURRENT', $pagination->getPage());
            $template->assign('LAST', $pagination->getLastPage());

            $repeat = true;
        }

        if ($content !== null) {
            return $content;
        }
    }

    /**
     * Check if a loop has returned results. The loop shoud have been executed before, or an
     * InvalidArgumentException is thrown
     *
     * @param  unknown                   $params
     * @param  unknown                   $template
     * @throws \InvalidArgumentException
     */
    protected function checkEmptyLoop($params, $template)
    {

    	if (empty($params['rel']))
    		throw new \InvalidArgumentException("Missing 'rel' parameter in ifloop/elseloop arguments");

        $loopName = $params['rel'];

        if (! isset($this->loopstack[$loopName])) {
            throw new \InvalidArgumentException("Loop $loopName is not defined.");
        }

        return $this->loopstack[$loopName]->isEmpty();
    }

    /**
     *
     * find the loop class with his name and construct an instance of this class
     *
     * @param  string                                          $name
     * @return \Thelia\Core\Template\Element\BaseLoop
     * @throws InvalidElementException
     * @throws ElementNotFoundException
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
     * @param BaseLoop $loop a BaseLoop instance
     * @param  $smartyParam
     * @throws \InvalidArgumentException
     */
    protected function getLoopArgument(BaseLoop $loop, $smartyParam)
    {
    	$faultActor = array();
    	$faultDetails = array();

        $argumentsCollection = $loop->getArgs();
        foreach( $argumentsCollection as $argument ) {

            $value = isset($smartyParam[$argument->name]) ? (string)$smartyParam[$argument->name] : null;

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

            /* check type */
            if($value !== null && !$argument->type->isValid($value)) {
                $faultActor[] = $argument->name;
                $faultDetails[] = sprintf('Invalid value for "%s" argument', $argument->name);
                continue;
            }

            /* set default */
            /* did it as last checking for we consider default value is acceptable no matter type or empty restriction */
            if($value === null && $argument->default !== null) {
                $value = (string)$argument->default;
            }

            $loop->{$argument->name} = $value === null ? null : $argument->type->getFormatedValue($value);
        }

        if (!empty($faultActor)) {

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
     * @param  array                     $loopDefinition
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
    		new SmartyPluginDescriptor('block', 'ifloop'   , $this, 'theliaIfLoop'),
    		new SmartyPluginDescriptor('block', 'pageloop'   , $this, 'theliaPageLoop'),
        );
     }
}
