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

class CountryCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Country title *"),
                "label_attr" => array(
                    "for" => "title",
                ),
            ))
            ->add("locale", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label_attr" => array("for" => "locale_create"),
            ))
            ->add("area", "text", array(
                "label" => Translator::getInstance()->trans("Country area"),
                "label_attr" => array(
                    "for" => "area",
                ),
            ))
            ->add("isocode", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("ISO Code *"),
                "label_attr" => array(
                    "for" => "isocode",
                ),
            ))
            ->add("isoalpha2", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Alpha code 2 *"),
                "label_attr" => array(
                    "for" => "isoalpha2",
                ),
            ))
            ->add("isoalpha3", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Alpha code 3 *"),
                "label_attr" => array(
                    "for" => "isoalpha3",
                ),
            ))
        ;
    }

    public function getName()
    {
        return "thelia_country_creation";
    }
}
