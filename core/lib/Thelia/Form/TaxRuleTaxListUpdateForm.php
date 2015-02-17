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
use Thelia\Model\CountryQuery;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Type\JsonType;

class TaxRuleTaxListUpdateForm extends BaseForm
{
    protected function buildForm()
    {
        $countryList = array();
        foreach (CountryQuery::create()->find() as $country) {
            $countryList[$country->getId()] = $country->getId();
        }

        $this->formBuilder
            ->add("id", "hidden", array(
                    "required" => true,
                    "constraints" => array(
                        new Constraints\NotBlank(),
                        new Constraints\Callback(
                            array(
                                "methods" => array(
                                    array($this, "verifyTaxRuleId"),
                                ),
                            )
                        ),
                    ),
            ))
            ->add("tax_list", "hidden", array(
                "required" => true,
                "attr" => array(
                    "id" => 'tax_list',
                ),
                "constraints" => array(
                    new Constraints\Callback(
                        array(
                            "methods" => array(
                                array($this, "verifyTaxList"),
                            ),
                        )
                    ),
                ),
            ))
            ->add("country_list", "choice", array(
                "choices" => $countryList,
                "required" => true,
                "multiple" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
            ))
        ;
    }

    public function getName()
    {
        return "thelia_tax_rule_taxlistupdate";
    }

    public function verifyTaxRuleId($value, ExecutionContextInterface $context)
    {
        $taxRule = TaxRuleQuery::create()
            ->findPk($value);

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
                            $context->addViolation(Translator::getInstance()->trans("Tax ID not found in tax list JSON"));
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
}
