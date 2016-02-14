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
use Thelia\Model\TaxQuery;

/**
 * Class TaxModificationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxModificationForm extends TaxCreationForm
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
                                    array($this, "verifyTaxId"),
                                ),
                            )
                        ),
                    ),
            ))
        ;
    }

    public function getName()
    {
        return "thelia_tax_modification";
    }

    public function verifyTaxId($value, ExecutionContextInterface $context)
    {
        $tax = TaxQuery::create()
            ->findPk($value);

        if (null === $tax) {
            $context->addViolation("Tax ID not found");
        }
    }
}
