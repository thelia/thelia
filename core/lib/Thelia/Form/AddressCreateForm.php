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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

/**
 * Class AddressCreateForm
 * @package Thelia\Form
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AddressCreateForm extends FirewallForm
{
    use AddressCountryValidationTrait;

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     * $this->formBuilder->add("name", TextType::class)
     *   ->add("email", EmailType::class, array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', IntegerType::class);
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add("label", TextType::class, array(
                    "constraints" => array(
                        new NotBlank(),
                    ),
                    "label" => Translator::getInstance()->trans("Address label"),
                    "label_attr" => array(
                        "for" => "address_label",
                    ),
                ))
            ->add("title", TextType::class, array(
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => Translator::getInstance()->trans("Title"),
                    "label_attr" => array(
                        "for" => "title",
                    ),
                ))
            ->add("firstname", TextType::class, array(
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => Translator::getInstance()->trans("First Name"),
                    "label_attr" => array(
                        "for" => "firstname",
                    ),
                ))
            ->add("lastname", TextType::class, array(
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => Translator::getInstance()->trans("Last Name"),
                    "label_attr" => array(
                        "for" => "lastname",
                    ),
                ))
            ->add("company", TextType::class, array(
                    "label" => Translator::getInstance()->trans("Company Name"),
                    "label_attr" => array(
                        "for" => "company",
                    ),
                    "required" => false,
                ))
            ->add("address1", TextType::class, array(
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => Translator::getInstance()->trans("Street Address"),
                    "label_attr" => array(
                        "for" => "address1",
                    ),
                ))
            ->add("address2", TextType::class, array(
                    "label" => Translator::getInstance()->trans("Address Line 2"),
                    "label_attr" => array(
                        "for" => "address2",
                    ),
                    "required" => false,
                ))
            ->add("address3", TextType::class, array(
                    "label" => Translator::getInstance()->trans("Address Line 3"),
                    "label_attr" => array(
                        "for" => "address3",
                    ),
                    "required" => false,
                ))
            ->add("city", TextType::class, array(
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => Translator::getInstance()->trans("City"),
                    "label_attr" => array(
                        "for" => "city",
                    ),
                ))
            ->add("zipcode", TextType::class, array(
                    "constraints" => array(
                        new Constraints\NotBlank(),
                        new Constraints\Callback(array(
                            "methods" => array(
                                array($this, "verifyZipCode")
                            ),
                        )),
                    ),
                    "label" => Translator::getInstance()->trans("Zip code"),
                    "label_attr" => array(
                        "for" => "zipcode",
                    ),
                ))
            ->add("country", TextType::class, array(
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => Translator::getInstance()->trans("Country"),
                    "label_attr" => array(
                        "for" => "country",
                    ),
                ))
            ->add("state", TextType::class, array(
                "required" => false,
                "constraints" => array(
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyState")
                        ),
                    )),
                ),

                "label" => Translator::getInstance()->trans("State *"),
                "label_attr" => array(
                    "for" => "state",
                ),
            ))
            // Phone
            ->add("phone", TextType::class, array(
                    "label" => Translator::getInstance()->trans("Phone"),
                    "label_attr" => array(
                        "for" => "phone",
                    ),
                    "required" => false,
                ))
            ->add("cellphone", TextType::class, array(
                    "label" => Translator::getInstance()->trans("Cellphone"),
                    "label_attr" => array(
                        "for" => "cellphone",
                    ),
                    "required" => false,
                ))
            // Default address
            ->add("is_default", CheckboxType::class, array(
                    "label" => Translator::getInstance()->trans("Make this address as my primary address"),
                    "label_attr" => array(
                        "for" => "default_address",
                    ),
                    "required" => false,
                ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_address_creation";
    }
}
