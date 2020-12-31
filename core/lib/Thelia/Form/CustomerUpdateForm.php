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
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
     * @return null|void
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'update_logged_in_user',
                IntegerType::class// In a front office context, update the in-memory logged-in user data
            )
            ->add("company", TextType::class, array(
                "label" => Translator::getInstance()->trans("Company"),
                "label_attr" => array(
                    "for" => "company",
                ),
                "required" => false,
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
            ->add("email", EmailType::class, array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Email(),
                ),
                "label" => Translator::getInstance()->trans("Email address"),
                "label_attr" => array(
                    "for" => "email",
                ),
            ))
            ->add("email_confirm", EmailType::class, array(
                "constraints" => array(
                    new Constraints\Email(),
                    new Constraints\Callback(array($this, "verifyEmailField")),
                ),
                "label" => Translator::getInstance()->trans("Confirm Email address"),
                "label_attr" => array(
                    "for" => "email_confirm",
                ),
            ))
            ->add("password", TextType::class, array(
                "label" => Translator::getInstance()->trans("Password"),
                "required" => false,
                "label_attr" => array(
                    "for" => "password",
                    "help" => Translator::getInstance()->trans('Leave blank to keep current customer password')
                ),
            ))
            ->add("address1", TextType::class, array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label_attr" => array(
                    "for" => "address",
                ),
                "label" => Translator::getInstance()->trans("Street Address "),
            ))
            ->add("address2", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Address Line 2"),
                "label_attr" => array(
                    "for" => "address2",
                ),
            ))
            ->add("address3", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Address Line 3"),
                "label_attr" => array(
                    "for" => "address3",
                ),
            ))
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
            ->add("zipcode", TextType::class, array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                            array($this, "verifyZipCode")),
                    ),
                "label" => Translator::getInstance()->trans("Zip code"),
                "label_attr" => array(
                    "for" => "zipcode",
                ),
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
                    new Constraints\Callback(
                            array($this, "verifyState")
                        ),
                ),
                "label" => Translator::getInstance()->trans("State *"),
                "label_attr" => array(
                    "for" => "state",
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
            ->add('discount', TextType::class, array(
                'required' => false,
                'label' => Translator::getInstance()->trans('permanent discount (in percent)'),
                'label_attr' => array(
                    'for' => 'discount',
                ),
            ))
            ->add('reseller', CheckboxType::class, array(
                'required' => false,
                'label' => Translator::getInstance()->trans('Reseller'),
                'label_attr' => array(
                    'for' => 'reseller',
                ),
            ))
            ->add('lang_id', IntegerType::class, array(
                'required' => false,
                'label' => Translator::getInstance()->trans('Preferred language'),
                'label_attr' => array(
                    'for' => 'lang_id',
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
