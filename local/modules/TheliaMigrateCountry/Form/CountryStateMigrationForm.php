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

namespace TheliaMigrateCountry\Form;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Valid;
use Thelia\Form\BaseForm;
use TheliaMigrateCountry\Form\Type\CountryStateMigrationType;

/**
 * Class CountryStateMigrationForm.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class CountryStateMigrationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'migrations',
                CollectionType::class,
                [
                    'entry_type' => CountryStateMigrationType::class,
                    'allow_add' => true,
                    'required' => true,
                    'constraints' => [
                        new Count(['min' => 1]),
                        new Valid(),
                    ],
                ]
            )
        ;
    }

    public static function getName()
    {
        return 'thelia_country_state_migration';
    }
}
