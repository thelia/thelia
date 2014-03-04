<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
