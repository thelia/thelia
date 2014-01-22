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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class CategoryCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Category title *"),
                "label_attr" => array(
                    "for" => "title"
                )
            ))
            ->add("parent", "text", array(
                "label" => Translator::getInstance()->trans("Parent category *"),
                "constraints" => array(
                    new NotBlank()
                ),
               "label_attr" => array("for" => "parent_create")
            ))
            ->add("locale", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
               "label_attr" => array("for" => "locale_create")
            ))
            ->add("visible", "integer", array(
                "label" => Translator::getInstance()->trans("This category is online."),
                "label_attr" => array("for" => "visible_create")
            ))
        ;
    }

    public function getName()
    {
        return "thelia_category_creation";
    }
}
