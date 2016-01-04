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

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\OrderAddressQuery;

/**
 * Class AddressUpdateForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderUpdateAddress extends BaseForm
{
    use AddressCountryValidationTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add("id", "integer", array(
                "constraints" => array(
                    new NotBlank(),
                    new Callback(array(
                        "methods" => array(
                            array($this, "verifyId"),
                        ),
                    )),
                ),
                "required" => true,
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank(),
                    new Callback(array(
                        "methods" => array(
                            array($this, "verifyTitle"),
                        ),
                    )),
                ),
                "label" => Translator::getInstance()->trans("Title"),
                "label_attr" => array(
                    "for" => "title_update",
                ),
            ))
            ->add("firstname", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Firstname"),
                "label_attr" => array(
                    "for" => "firstname_update",
                ),
            ))
            ->add("lastname", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Lastname"),
                "label_attr" => array(
                    "for" => "lastname_update",
                ),
            ))
            ->add("address1", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Street Address"),
                "label_attr" => array(
                    "for" => "address1_update",
                ),
            ))
            ->add("address2", "text", array(
                "label" => Translator::getInstance()->trans("Additional address"),
                "label_attr" => array(
                    "for" => "address2_update",
                ),
            ))
            ->add("address3", "text", array(
                "label" => Translator::getInstance()->trans("Additional address"),
                "label_attr" => array(
                    "for" => "address3_update",
                ),
            ))
            ->add("zipcode", "text", array(
                "constraints" => array(
                    new NotBlank(),
                    new Callback(array(
                        "methods" => array(
                            array($this, "verifyZipCode")
                        ),
                    )),
                ),
                "label" => Translator::getInstance()->trans("Zip code"),
                "label_attr" => array(
                    "for" => "zipcode_update",
                ),
            ))
            ->add("city", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("City"),
                "label_attr" => array(
                    "for" => "city_update",
                ),
            ))
            ->add("country", "text", array(
                "constraints" => array(
                    new NotBlank(),
                    new Callback(array(
                        "methods" => array(
                            array($this, "verifyCountry"),
                        ),
                    )),
                ),
                "label" => Translator::getInstance()->trans("Country"),
                "label_attr" => array(
                    "for" => "country_update",
                ),
            ))
            ->add("state", "text", array(
                "constraints" => array(
                    new Callback(array(
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
            ->add("phone", "text", array(
                "label" => Translator::getInstance()->trans("Phone"),
                "label_attr" => array(
                    "for" => "phone_update",
                ),
            ))
            ->add("cellphone", "text", array(
                "label" => Translator::getInstance()->trans("Cellphone"),
                "label_attr" => array(
                    "for" => "cellphone_update",
                ),
            ))
            ->add("company", "text", array(
                "label" => Translator::getInstance()->trans("Compagny"),
                "label_attr" => array(
                    "for" => "company_update",
                ),
            ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_order_address_update";
    }

    public function verifyId($value, ExecutionContextInterface $context)
    {
        $address = OrderAddressQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans("Order address ID not found"));
        }
    }

    public function verifyTitle($value, ExecutionContextInterface $context)
    {
        $address = CustomerTitleQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans("Title ID not found"));
        }
    }

    public function verifyCountry($value, ExecutionContextInterface $context)
    {
        $address = CountryQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans("Country ID not found"));
        }
    }
}
