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
use Symfony\Component\Validator\Constraints\GreaterThan;

class MessageModificationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("id"           , "hidden", array("constraints" => array(new GreaterThan(array('value' => 0)))))
            ->add("name"         , "text"  , array(
                "constraints" => array(new NotBlank()),
                "label" => "Name *",
                "label_attr" => array(
                    "for" => "name"
                )
            ))
            ->add("secured"      , "text"  , array(
                "label" => "Prevent mailing template modification or deletion, except for super-admin"
            ))
            ->add("locale"       , "text"  , array())
            ->add("title"        , "text"  , array(
                "constraints" => array(new NotBlank()),
                "label" => "Title *",
                "label_attr" => array(
                    "for" => "title"
                )
            ))
            ->add("subject"      , "text"  , array(
                "constraints" => array(new NotBlank()),
                "label" => "Message subject *",
                "label_attr" => array(
                    "for" => "subject"
                )
            ))
            ->add("html_message" , "text"  , array(
                "label" => "HTML Message",
                "label_attr" => array(
                    "for" => "html_message"
                )
            ))
            ->add("text_message" , "text"  , array(
                "label" => "Text Message",
                "label_attr" => array(
                    "for" => "text_message"
                )
            ))
            ;
    }

    public function getName()
    {
        return "thelia_message_modification";
    }
}
