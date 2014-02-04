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

/**
 * A trait to add standard localized description fields to a form.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
trait StandardDescriptionFieldsTrait
{
    /**
     * Add standard description fields + locale tot the form
     *
     * @param array $exclude name of the fields that should not be added to the form
     */
    protected function addStandardDescFields($exclude = array())
    {
        if (! in_array('locale', $exclude))
            $this->formBuilder
                ->add("locale", "hidden", array(
                        "constraints" => array(
                            new NotBlank()
                        )
                    )
                );

        if (! in_array('title', $exclude))
            $this->formBuilder
                ->add("title", "text", array(
                    "constraints" => array(
                        new NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Title"),
                    "label_attr" => array("for" => "title_field")
                )
            );

        if (! in_array('chapo', $exclude))
            $this->formBuilder
                ->add("chapo", "text", array(
                    "label" => Translator::getInstance()->trans("Summary"),
                    "label_attr" => array(
                        "for" => "summary_field"
                    )
                ));

        if (! in_array('description', $exclude))
            $this->formBuilder
                ->add("description", "text", array(
                    "label" => Translator::getInstance()->trans("Detailed description"),
                    "label_attr" => array(
                        "for" => "detailed_description_field"
                    )
                ));

        if (! in_array('postscriptum', $exclude))
            $this->formBuilder
                    ->add("postscriptum", "text", array(
                    "label" => Translator::getInstance()->trans("Conclusion"),
                    "label_attr" => array(
                        "for" => "conclusion_field"
                    )
                ));
     }
}
