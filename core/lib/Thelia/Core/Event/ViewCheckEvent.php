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

namespace Thelia\Core\Event;

class ViewCheckEvent extends ActionEvent
{
    protected $view;

    protected $view_id;

    public function __construct($view, $view_id)
    {
        $this->view = $view;

        $this->view_id = $view_id;
    }

    /**
     * @return mixed
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @param mixed $view
     *
     * @return $this
     */
    public function setView($view)
    {
        $this->view = $view;

        return $this;
    }

     /**
     * @return mixed
     */
    public function getViewId()
    {
        return $this->view_id;
    }

    /**
     * @param mixed $view_id
     *
     * @return $this
     */
    public function setViewId($view_id)
    {
        $this->view_id = $view_id;

        return $this;
    }
}