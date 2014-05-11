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
