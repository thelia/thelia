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

namespace TheliaMigrateCountry\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Form\Type\AbstractTheliaType;
use Thelia\Core\Form\Type\Field\CountryIdType;
use Thelia\Core\Form\Type\Field\StateIdType;
use Thelia\Core\Translation\Translator;
use Thelia\Model\StateQuery;

/**
 * Class CountryStateMigrationType.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class CountryStateMigrationType extends AbstractTheliaType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'constraints' => [
                    new Callback([$this, 'checkStateId']),
                    new Valid(),
                ],
            ]
        );
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('migrate', CheckboxType::class)
            ->add(
                'country',
                CountryIdType::class
            )
            ->add(
                'new_country',
                CountryIdType::class
            )
            ->add(
                'new_state',
                StateIdType::class,
                [
                    'constraints' => [],
                ]
            )
        ;
    }

    public function checkStateId($value, ExecutionContextInterface $context): void
    {
        if ($value['migrate']) {
            if (null !== $state = StateQuery::create()->findPk($value['new_state'])) {
                if ($state->getCountryId() !== $value['new_country']) {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            "The state id '%id' does not belong to country id '%id_country'",
                            [
                                '%id' => $value['new_state'],
                                '%id_country' => $value['new_country'],
                            ]
                        )
                    );
                }
            } else {
                $context->addViolation(
                    Translator::getInstance()->trans(
                        "The state id '%id' doesn't exist",
                        ['%id' => $value['new_state']]
                    )
                );
            }
        }
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName(): string
    {
        return 'country_state_migration';
    }
}
