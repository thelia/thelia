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

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\CountryQuery;
use Thelia\Model\StateQuery;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Type\JsonType;

class TaxRuleTaxListUpdateForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'id',
                HiddenType::class,
                [
                    'required' => true,
                    'constraints' => [
                        new NotBlank(),
                        new Callback(
                            $this->verifyTaxRuleId(...),
                        ),
                    ],
                ],
            )
            ->add(
                'tax_list',
                HiddenType::class,
                [
                    'required' => true,
                    'attr' => [
                        'id' => 'tax_list',
                    ],
                    'constraints' => [
                        new Callback(
                            $this->verifyTaxList(...),
                        ),
                    ],
                ],
            )
            ->add(
                'country_list',
                HiddenType::class,
                [
                    'required' => true,
                    'attr' => [
                        'id' => 'country_list',
                    ],
                    'constraints' => [
                        new Callback(
                            $this->verifyCountryList(...),
                        ),
                    ],
                ],
            )
            ->add(
                'country_deleted_list',
                HiddenType::class,
                [
                    'required' => true,
                    'attr' => [
                        'id' => 'country_deleted_list',
                    ],
                    'constraints' => [
                        new Callback(
                            $this->verifyCountryList(...),
                        ),
                    ],
                ],
            );
    }

    public static function getName(): string
    {
        return 'thelia_tax_rule_taxlistupdate';
    }

    public function verifyTaxRuleId($value, ExecutionContextInterface $context): void
    {
        $taxRule = TaxRuleQuery::create()
            ->findPk($value);

        if (null === $taxRule) {
            $context->addViolation(Translator::getInstance()->trans('Tax rule ID not found'));
        }
    }

    public function verifyTaxList($value, ExecutionContextInterface $context): void
    {
        $jsonType = new JsonType();

        if (!$jsonType->isValid($value)) {
            $context->addViolation(Translator::getInstance()->trans('Tax list is not valid JSON'));
        }

        $taxList = json_decode((string) $value, true);

        /* check we have 2 level max */

        foreach ($taxList as $taxLevel1) {
            if (\is_array($taxLevel1)) {
                foreach ($taxLevel1 as $taxLevel2) {
                    if (\is_array($taxLevel2)) {
                        $context->addViolation(Translator::getInstance()->trans('Bad tax list JSON'));
                    } else {
                        $taxModel = TaxQuery::create()->findPk($taxLevel2);

                        if (null === $taxModel) {
                            $context->addViolation(Translator::getInstance()
                                ->trans('Tax ID not found in tax list JSON'));
                        }
                    }
                }
            } else {
                $taxModel = TaxQuery::create()->findPk($taxLevel1);

                if (null === $taxModel) {
                    $context->addViolation(Translator::getInstance()->trans('Tax ID not found in tax list JSON'));
                }
            }
        }
    }

    public function verifyCountryList($value, ExecutionContextInterface $context): void
    {
        $jsonType = new JsonType();

        if (!$jsonType->isValid($value)) {
            $context->addViolation(Translator::getInstance()->trans('Country list is not valid JSON'));
        }

        $countryList = json_decode((string) $value, true);

        foreach ($countryList as $countryItem) {
            if (\is_array($countryItem)) {
                $country = CountryQuery::create()->findPk($countryItem[0]);

                if (null === $country) {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            'Country ID %id not found',
                            ['%id' => $countryItem[0]],
                        ),
                    );
                }

                if ('0' === $countryItem[1]) {
                    continue;
                }

                $state = StateQuery::create()->findPk($countryItem[1]);

                if (null === $state) {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            'State ID %id not found',
                            ['%id' => $countryItem[1]],
                        ),
                    );
                }
            } else {
                $context->addViolation(Translator::getInstance()->trans('Wrong country definition'));
            }
        }
    }
}
