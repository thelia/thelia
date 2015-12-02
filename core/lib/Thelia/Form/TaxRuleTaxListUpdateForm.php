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
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\CountryQuery;
use Thelia\Model\StateQuery;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Type\JsonType;

class TaxRuleTaxListUpdateForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "id",
                "hidden",
                [
                    "required" => true,
                    "constraints" => [
                        new Constraints\NotBlank(),
                        new Constraints\Callback(
                            [
                                "methods" => [
                                    [$this, "verifyTaxRuleId"],
                                ],
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                "tax_list",
                "hidden",
                [
                    "required" => true,
                    "attr" => [
                        "id" => 'tax_list',
                    ],
                    "constraints" => [
                        new Constraints\Callback(
                            [
                                "methods" => [
                                    [$this, "verifyTaxList"],
                                ],
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                "country_list",
                "hidden",
                [
                    "required" => true,
                    "attr" => [
                        "id" => 'country_list',
                    ],
                    "constraints" => [
                        new Constraints\Callback(
                            [
                                "methods" => [
                                    [$this, "verifyCountryList"],
                                ],
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                "country_deleted_list",
                "hidden",
                [
                    "required" => true,
                    "attr" => [
                        "id" => 'country_deleted_list',
                    ],
                    "constraints" => [
                        new Constraints\Callback(
                            [
                                "methods" => [
                                    [$this, "verifyCountryList"],
                                ],
                            ]
                        ),
                    ],
                ]
            )
        ;
    }

    public function getName()
    {
        return "thelia_tax_rule_taxlistupdate";
    }

    public function verifyTaxRuleId($value, ExecutionContextInterface $context)
    {
        $taxRule = TaxRuleQuery::create()
            ->findPk($value)
        ;

        if (null === $taxRule) {
            $context->addViolation(Translator::getInstance()->trans("Tax rule ID not found"));
        }
    }

    public function verifyTaxList($value, ExecutionContextInterface $context)
    {
        $jsonType = new JsonType();
        if (!$jsonType->isValid($value)) {
            $context->addViolation(Translator::getInstance()->trans("Tax list is not valid JSON"));
        }

        $taxList = json_decode($value, true);

        /* check we have 2 level max */

        foreach ($taxList as $taxLevel1) {
            if (is_array($taxLevel1)) {
                foreach ($taxLevel1 as $taxLevel2) {
                    if (is_array($taxLevel2)) {
                        $context->addViolation(Translator::getInstance()->trans("Bad tax list JSON"));
                    } else {
                        $taxModel = TaxQuery::create()->findPk($taxLevel2);
                        if (null === $taxModel) {
                            $context->addViolation(Translator::getInstance()
                                ->trans("Tax ID not found in tax list JSON"));
                        }
                    }
                }
            } else {
                $taxModel = TaxQuery::create()->findPk($taxLevel1);
                if (null === $taxModel) {
                    $context->addViolation(Translator::getInstance()->trans("Tax ID not found in tax list JSON"));
                }
            }
        }
    }

    public function verifyCountryList($value, ExecutionContextInterface $context)
    {
        $jsonType = new JsonType();
        if (!$jsonType->isValid($value)) {
            $context->addViolation(Translator::getInstance()->trans("Country list is not valid JSON"));
        }

        $countryList = json_decode($value, true);

        foreach ($countryList as $countryItem) {
            if (is_array($countryItem)) {
                $country = CountryQuery::create()->findPk($countryItem[0]);
                if (null === $country) {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            "Country ID %id not found",
                            ['%id' => $countryItem[0]]
                        )
                    );
                }

                if ($countryItem[1] == "0") {
                    continue;
                }

                $state = StateQuery::create()->findPk($countryItem[1]);
                if (null === $state) {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            "State ID %id not found",
                            ['%id' => $countryItem[1]]
                        )
                    );
                }
            } else {
                $context->addViolation(Translator::getInstance()->trans("Wrong country definition"));
            }
        }
    }
}
