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
use Thelia\Model\TaxRuleQuery;

class TaxRuleModificationForm extends TaxRuleCreationForm
{
    protected function buildForm()
    {
        parent::buildForm(true);

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
        ;
    }

    public function getName()
    {
        return "thelia_tax_rule_modification";
    }

    public function verifyTaxRuleId($value, ExecutionContextInterface $context)
    {
        $taxRule = TaxRuleQuery::create()
            ->findPk($value);

        if (null === $taxRule) {
            $context->addViolation(Translator::getInstance()->trans("Tax rule ID not found"));
        }
    }
}
