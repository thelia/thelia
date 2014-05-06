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

namespace Thelia\Core\Event\Template;

class TemplateUpdateEvent extends TemplateCreateEvent
{
    protected $template_id;

    protected $feature_list;
    protected $attribute_list;

    public function __construct($template_id)
    {
        $this->setTemplateId($template_id);
    }

    public function getTemplateId()
    {
        return $this->template_id;
    }

    public function setTemplateId($template_id)
    {
        $this->template_id = $template_id;

        return $this;
    }

    public function getFeatureList()
    {
        return $this->feature_list;
    }

    public function setFeatureList($feature_list)
    {
        $this->feature_list = $feature_list;

        return $this;
    }

    public function getAttributeList()
    {
        return $this->attribute_list;
    }

    public function setAttributeList($attribute_list)
    {
        $this->attribute_list = $attribute_list;

        return $this;
    }
}
