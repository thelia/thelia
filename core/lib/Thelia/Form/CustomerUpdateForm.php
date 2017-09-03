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
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

/**
 * Class CustomerUpdateForm
 * @package Thelia\Form
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerUpdateForm extends BaseForm
{
    use AddressCountryValidationTrait;

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->form attribute :
     *
     * $this->form->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add('update_logged_in_user',
                'integer')// In a front office context, update the in-memory logged-in user data
            ->add("company", "text", array(
                "label" => Translator::getInstance()->trans("Company"),
                "label_attr" => array(
                    "for" => "company",
                ),
                "required" => false,
            ))
            ->add("firstname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("First Name"),
                "label_attr" => array(
                    "for" => "firstname",
                ),
            ))
            ->add("lastname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Last Name"),
                "label_attr" => array(
                    "for" => "lastname",
                ),
            ))
            ->add("email", "email", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Email(),
                ),
                "label" => Translator::getInstance()->trans("Email address"),
                "label_attr" => array(
                    "for" => "email",
                ),
            ))
            ->add("email_confirm", "email", array(
                "constraints" => array(
                    new Constraints\Email(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyEmailField"),
                        )
                    )),
                ),
                "label" => Translator::getInstance()->trans("Confirm Email address"),
                "label_attr" => array(
                    "for" => "email_confirm",
                ),
            ))
            ->add("password", "text", array(
                "label" => Translator::getInstance()->trans("Password"),
                "label_attr" => array(
                    "for" => "password",
                ),
            ))
            ->add("address1", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label_attr" => array(
                    "for" => "address",
                ),
                "label" => Translator::getInstance()->trans("Street Address "),
            ))
            ->add("address2", "text", array(
                "label" => Translator::getInstance()->trans("Address Line 2"),
                "label_attr" => array(
                    "for" => "address2",
                ),
            ))
            ->add("address3", "text", array(
                "label" => Translator::getInstance()->trans("Address Line 3"),
                "label_attr" => array(
                    "for" => "address3",
                ),
            ))
            ->add("phone", "text", array(
                "label" => Translator::getInstance()->trans("Phone"),
                "label_attr" => array(
                    "for" => "phone",
                ),
                "required" => false,
            ))
            ->add("cellphone", "text", array(
                "label" => Translator::getInstance()->trans("Cellphone"),
                "label_attr" => array(
                    "for" => "cellphone",
                ),
                "required" => false,
            ))
            ->add("zipcode", "text", array(
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
            ->add("city", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("City"),
                "label_attr" => array(
                    "for" => "city",
                ),
            ))
            ->add("country", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Country"),
                "label_attr" => array(
                    "for" => "country",
                ),
            ))
            ->add("state", "text", array(
                "constraints" => array(
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyState")
                        ),
                    )),
                ),
                "label" => Translator::getInstance()->trans("State"),
                "label_attr" => array(
                    "for" => "state",
                ),
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Title"),
                "label_attr" => array(
                    "for" => "title",
                ),
            ))
            ->add('discount', 'text', array(
                'label' => Translator::getInstance()->trans('permanent discount (in percent)'),
                'label_attr' => array(
                    'for' => 'discount',
                ),
            ))
            ->add('reseller', 'integer', array(
                'label' => Translator::getInstance()->trans('Reseller'),
                'label_attr' => array(
                    'for' => 'reseller',
                ),
            ))
        ;
    }

    public function verifyEmailField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if (isset($data["email_confirm"]) && $data["email"] != $data["email_confirm"]) {
            $context->addViolation(
                Translator::getInstance()->trans("email confirmation is not the same as email field")
            );
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_customer_update";
    }
}
