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

namespace Thelia\Core\Event\Config;

class ConfigCreateEvent extends ConfigEvent
{
    protected $event_name;
    protected $value;
    protected $locale;
    protected $title;
    protected $hidden;
    protected $secured;

    // Use event_name to prevent conflict with Event::name property.
    public function getEventName()
    {
        return $this->event_name;
    }

    public function setEventName($event_name)
    {
        $this->event_name = $event_name;

        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getHidden()
    {
        return $this->hidden;
    }

    public function setHidden($hidden)
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function getSecured()
    {
        return $this->secured;
    }

    public function setSecured($secured)
    {
        $this->secured = $secured;

        return $this;
    }
}
