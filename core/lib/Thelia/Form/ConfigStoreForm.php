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

use Symfony\Component\Validator\Constraints;
use Thelia\Core\Translation\Translator;

class ConfigStoreForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("store_name", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans('Store name'),
                "label_attr" => array(
                        "for" => "store_name"
                )
            ))
            ->add("store_description", "text", array(
                    "label" => Translator::getInstance()->trans('Store description'),
                    "label_attr" => array(
                        "for" => "store_description"
                    ),
                    "required" => false
                ))
            ->add("store_email", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Email()
                ),
                "label" => Translator::getInstance()->trans('Store email address'),
                "label_attr" => array(
                    "for" => "store_email"
                )
            ))
            ->add("store_business_id", "text", array(
                "label" => Translator::getInstance()->trans('Business ID'),
                "label_attr" => array(
                    "for" => "store_business_id"
                ),
                "required" => false
            ))
            ->add("store_phone", "text", array(
                "label" => Translator::getInstance()->trans("Phone"),
                "label_attr" => array(
                    "for" => "store_phone"
                ),
                "required" => false
            ))
            ->add("store_fax", "text", array(
                "label" => Translator::getInstance()->trans("Fax"),
                "label_attr" => array(
                    "for" => "store_fax"
                ),
                "required" => false
            ))
            ->add("store_address1", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Street Address"),
                "label_attr" => array(
                    "for" => "store_address1"
                )
            ))
            ->add("store_address2", "text", array(
                "label" => Translator::getInstance()->trans("Address Line 2"),
                "label_attr" => array(
                    "for" => "store_address2"
                ),
                "required" => false
            ))
            ->add("store_address3", "text", array(
                "label" => Translator::getInstance()->trans("Address Line 3"),
                "label_attr" => array(
                    "for" => "store_address3"
                ),
                "required" => false
            ))
            ->add("store_zipcode", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Zip code"),
                "label_attr" => array(
                    "for" => "store_zipcode"
                )
            ))
            ->add("store_city", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("City"),
                "label_attr" => array(
                    "for" => "store_city"
                )
            ))
            ->add("store_country", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Country"),
                "label_attr" => array(
                    "for" => "store_country"
                )
            ))
            ;
    }

    public function getName()
    {
        return "thelia_configuration_store";
    }
}
