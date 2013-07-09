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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Element;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 *
 * Class BaseLoop
 * @package Thelia\Tpex\Element\Loop
 */
abstract class BaseLoop
{
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    private $args;

    protected function getDefaultArgs()
    {
        return array(
            Argument::createIntTypeArgument('offset', 0),
            Argument::createIntTypeArgument('page'),
            Argument::createIntTypeArgument('limit', 10),
        );
    }

    /**
     * Create a new Loop
     *
     * @param \Symfony\Component\HttpFoundation\Request                   $request
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct(Request $request, EventDispatcherInterface $dispatcher)
    {
        $this->request = $request;
        $this->dispatcher = $dispatcher;

        $this->args = $this->getArgDefinitions()->addArguments($this->getDefaultArgs());
    }

    /**
     * Initialize the loop arguments.
     *
     * @param array $nameValuePairs a array of name => value pairs. The name is the name of the argument.
     *
     * @throws \InvalidArgumentException if somùe argument values are missing, or invalid
     */
    public function initializeArgs(array $nameValuePairs) {

        $faultActor = array();
        $faultDetails = array();

        while (($argument = $this->args->current()) !== false) {
            $this->args->next();

            $value = isset($nameValuePairs[$argument->name]) ? $nameValuePairs[$argument->name] : null;

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
            if($value === null) {
                $value = $argument->default;
            }

            $argument->setValue($value);
        }

        if (!empty($faultActor)) {

            $complement = sprintf('[%s]', implode(', ', $faultDetails));
            throw new \InvalidArgumentException($complement);
        }
    }

    /**
     * Return a loop argument
     *
     * @param string $argumentName the argument name
     *
     * @throws \InvalidArgumentException if argument is not found in loop argument list
     * @return Argument the loop argument.
     */
    public function getArg($argumentName) {

    	$arg = $this->args->get($argumentName);

    	if ($arg === null)
    		throw new \InvalidArgumentException("Undefined loop argument '$argumentName'");

    	return $arg;
    }

    /**
     * Return a loop argument value
     *
     * @param string $argumentName the argument name
     *
     * @throws \InvalidArgumentException if argument is not found in loop argument list
     * @return Argument the loop argument.
     */
    public function getArgValue($argumentName) {

    	return $this->getArg($argumentName)->getValue();
    }

    /**
     * @param \ModelCriteria $search
     * @param null           $pagination
     *
     * @return array|mixed|\PropelModelPager|\PropelObjectCollection
     */
    public function search(ModelCriteria $search, &$pagination = null)
    {
        if($this->getArgValue('page') !== null) {
            return $this->searchWithPagination($search, $pagination);
        } else {
            return $this->searchWithOffset($search);
        }
    }

    /**
     * @param \ModelCriteria $search
     *
     * @return array|mixed|\PropelObjectCollection
     */
    public function searchWithOffset(ModelCriteria $search)
    {
        if($this->getArgValue('limit') >= 0) {
            $search->limit($this->getArgValue('limit'));
        }
        $search->offset($this->getArgValue('offset'));

        return $search->find();
    }

    /**
     * @param \ModelCriteria $search
     * @param                $pagination
     *
     * @return array|\PropelModelPager
     */
    public function searchWithPagination(ModelCriteria $search, &$pagination)
    {
        $pagination = $search->paginate($this->getArgValue('page'), $this->getArgValue('limit'));

        if($this->getArgValue('page') > $pagination->getLastPage()) {
            return array();
        } else {
            return $pagination;
        }
    }

    /**
     *
     * this function have to be implement in your own loop class.
     *
     * All your parameters are defined in defineArgs() and can be accessible like a class property.
     *
     * example :
     *
     * public function defineArgs()
     * {
     *  return array (
     *      "ref",
     *      "id" => "optional",
     *      "stock" => array(
     *          "optional",
     *          "default" => 10
     *          )
     *  );
     * }
     *
     * you can retrieve ref value using $this->ref
     *
     * @param $pagination
     *
     * @return mixed
     */
    abstract public function exec(&$pagination);

    /**
     *
     * define all args used in your loop
     *
     * array key is your arg name.
     *
     * example :
     *
     * return array (
     *  "ref",
     *  "id" => "optional",
     *  "stock" => array(
     *          "optional",
     *          "default" => 10
     *          )
     * );
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    abstract protected function getArgDefinitions();

}
