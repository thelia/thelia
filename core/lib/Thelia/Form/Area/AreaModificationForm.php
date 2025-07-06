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

namespace Thelia\Form\Area;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\GreaterThan;

/**
 * Class AreaModificationForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaModificationForm extends AreaCreateForm
{
    protected function buildForm(): void
    {
        parent::buildForm();

        $this->formBuilder
            ->add(
                'area_id',
                HiddenType::class,
                [
                    'constraints' => [
                        new GreaterThan(['value' => 0]),
                    ],
                ]
            )
        ;
    }

    public static function getName(): string
    {
        return 'thelia_area_modification';
    }
}
