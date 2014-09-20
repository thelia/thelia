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

namespace Thelia\Core\Event\Attribute;

class AttributeCreateEvent extends AttributeEvent
{
    protected $title;
    protected $locale;
    protected $add_to_all_templates;

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

    public function getAddToAllTemplates()
    {
        return $this->add_to_all_templates;
    }

    public function setAddToAllTemplates($add_to_all_templates)
    {
        $this->add_to_all_templates = $add_to_all_templates;

        return $this;
    }
}
