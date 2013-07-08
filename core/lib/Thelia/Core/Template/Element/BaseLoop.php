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
    public $request;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public $dispatcher;

    public $limit;
    public $page;
    public $offset;

    protected function getDefaultArgs()
    {
        return array(
            Argument::createIntTypeArgument('offset', 0),
            Argument::createIntTypeArgument('page'),
            Argument::createIntTypeArgument('limit', 10),
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request                   $request
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct(Request $request, EventDispatcherInterface $dispatcher)
    {
        $this->request = $request;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    public function getArgs()
    {
        return $this->defineArgs()->addArguments($this->getDefaultArgs());
    }

    /**
     * @param \ModelCriteria $search
     * @param null           $pagination
     *
     * @return array|mixed|\PropelModelPager|\PropelObjectCollection
     */
    public function search(ModelCriteria $search, &$pagination = null)
    {
        if($this->page !== null) {
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
        if($this->limit >= 0) {
            $search->limit($this->limit);
        }
        $search->offset($this->offset);

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
        $pagination = $search->paginate($this->page, $this->limit);

        if($this->page > $pagination->getLastPage()) {
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
     */abstract public function exec(&$pagination);

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
    abstract protected function defineArgs();

}
