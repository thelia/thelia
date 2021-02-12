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
use Symfony\Component\Validator\Constraints\GreaterThan;

class FeatureModificationForm extends FeatureCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add('id', HiddenType::class, [
                    'constraints' => [
                        new GreaterThan(
                            ['value' => 0]
                        ),
                    ],
            ])
        ;

        // Add standard description fields
        $this->addStandardDescFields();
    }

    public static function getName()
    {
        return 'thelia_feature_modification';
    }
}
