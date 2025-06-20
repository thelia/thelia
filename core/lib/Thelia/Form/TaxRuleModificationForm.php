<?php

declare(strict_types=1);

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

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\TaxRuleQuery;

class TaxRuleModificationForm extends TaxRuleCreationForm
{
    protected function buildForm(): void
    {
        parent::buildForm();

        $this->formBuilder
            ->add('id', HiddenType::class, [
                    'required' => true,
                    'constraints' => [
                        new NotBlank(),
                        new Callback(
                            $this->verifyTaxRuleId(...)
                        ),
                    ],
            ])
        ;
    }

    public static function getName(): string
    {
        return 'thelia_tax_rule_modification';
    }

    public function verifyTaxRuleId($value, ExecutionContextInterface $context): void
    {
        $taxRule = TaxRuleQuery::create()
            ->findPk($value);

        if (null === $taxRule) {
            $context->addViolation(Translator::getInstance()->trans('Tax rule ID not found'));
        }
    }
}
