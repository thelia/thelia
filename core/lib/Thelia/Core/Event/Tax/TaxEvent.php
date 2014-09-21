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

namespace Thelia\Core\Event\Tax;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Tax;

class TaxEvent extends ActionEvent
{
    protected $tax = null;

    protected $locale;
    protected $id;
    protected $title;
    protected $description;
    protected $type;
    protected $requirements;

    public function __construct(Tax $tax = null)
    {
        $this->tax = $tax;
    }

    public function hasTax()
    {
        return ! is_null($this->tax);
    }

    public function getTax()
    {
        return $this->tax;
    }

    public function setTax(Tax $tax)
    {
        $this->tax = $tax;

        return $this;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setRequirements($requirements)
    {
        $this->requirements = $requirements;

        return $this;
    }

    public function getRequirements()
    {
        return $this->requirements;
    }
}
