<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

/**
 * Class ProfileCreationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProfileCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm($change_mode = false)
    {
        $types = ProfileEngine::getInstance()->getProfileTypeList();
        $typeList = array();
        $requirementList = array();
        foreach($types as $type) {
            $classPath = "\\Thelia\\ProfileEngine\\ProfileType\\$type";
            $instance = new $classPath();
            $typeList[$type] = $instance->getTitle();
            $requirementList[$type] = $instance->getRequirementsList();
        }

        $this->formBuilder
            ->add("locale", "text", array(
                "constraints" => array(new NotBlank())
            ))
            ->add("type", "choice", array(
                "choices" => $typeList,
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Type"),
                "label_attr" => array("for" => "type_field"),
            ))
        ;

        $this->addStandardDescFields(array('postscriptum', 'chapo', 'locale'));
    }

    public function getName()
    {
        return "thelia_profile_creation";
    }
}
