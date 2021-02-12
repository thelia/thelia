<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
        parent::buildForm();

        $this->formBuilder
            ->add("id", HiddenType::class, [
                    "required" => true,
                    "constraints" => [
                        new Constraints\NotBlank(),
                        new Constraints\Callback(
                            [$this, "verifyTaxId"]
                        ),
                    ],
            ])
        ;
    }

    public static function getName()
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
