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

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CurrencyQuery;

class CurrencyCreationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(true);

        $this->formBuilder
            ->add('name', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => $this->translator->trans('Name'),
                'label_attr' => [
                    'for' => 'name',
                    'help' => '&nbsp;',
                ],
                'attr' => [
                    'placeholder' => $this->translator->trans('Currency name'),
                ],
            ])
            ->add('locale', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('symbol', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => $this->translator->trans('Symbol'),
                'label_attr' => [
                    'for' => 'symbol',
                    'help' => $this->translator->trans('The symbol, such as &#36;, £, &euro;...'),
                ],
                'attr' => [
                    'placeholder' => $this->translator->trans('Symbol'),
                ],
            ])
            ->add('format', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => $this->translator->trans('Format'),
                'label_attr' => [
                    'for' => 'format',
                    'help' => $this->translator->trans('%n for number, %c for the currency code, %s for the currency symbol'),
                ],
                'attr' => [
                    'placeholder' => '%n',
                ],
            ])
            ->add('rate', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => $this->translator->trans(
                    'Rate from %currencyCode',
                    ['%currencyCode' => $defaultCurrency->getCode()]
                ),
                'label_attr' => [
                    'for' => 'rate',
                    'help' => $this->translator->trans(
                        'The rate from %currencyCode: Price in %currencyCode x rate = Price in this currency',
                        ['%currencyCode' => $defaultCurrency->getCode()]
                    ),
                ],
                'attr' => [
                    'placeholder' => $this->translator->trans('Rate'),
                ],
            ])
            ->add('code', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => $this->translator->trans('ISO 4217 code'),
                'label_attr' => [
                    'for' => 'iso_4217_code',
                    'help' => $this->translator->trans('More information about ISO 4217'),
                ],
                'attr' => [
                    'placeholder' => $this->translator->trans('Code'),
                ],
            ])
        ;
    }

    public static function getName(): string
    {
        return 'thelia_currency_creation';
    }

    public function checkDuplicateCode($value, ExecutionContextInterface $context): void
    {
        $currency = CurrencyQuery::create()->findOneByCode($value);

        if ($currency) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'A currency with code "%name" already exists.',
                    ['%name' => $value]
                )
            );
        }
    }
}
