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

namespace Thelia\Core\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Thelia\Core\Form\Type\Field\TaxRuleIdType;

/**
 * Class TaxRuleType.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TaxRuleType extends AbstractTheliaType
{
    public function __construct(protected TaxRuleIdType $taxRuleIdType)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'cascade_validation' => true,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('default', 'checkbox')
            ->add('country', 'collection', [
                'type' => 'country_id',
                'allow_add' => true,
                'allow_delete' => true,
                'cascade_validation' => 'true',
                'constraints' => [
                    new Count(['min' => 1]),
                ],
            ])
            ->add('tax', 'collection', [
                'type' => 'tax_id',
                'allow_add' => true,
                'allow_delete' => true,
                'cascade_validation' => 'true',
                'constraints' => [
                    new Count(['min' => 1]),
                ],
            ])
            ->add('i18n', 'collection', [
                'type' => 'tax_rule_i18n',
                'required' => true,
                'allow_add' => true,
                'cascade_validation' => true,
                'constraints' => [
                    new Count(['min' => 1]),
                ],
            ])
            ->add('id', 'tax_rule_id', [
                'constraints' => $this->getConstraints($this->taxRuleIdType, 'update'),
            ]);
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName(): string
    {
        return 'tax_rule';
    }
}
