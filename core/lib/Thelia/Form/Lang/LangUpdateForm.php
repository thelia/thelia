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

namespace Thelia\Form\Lang;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class LangUpdateForm
 * @package Thelia\Form\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangUpdateForm extends LangCreateForm
{
    public function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add('id', HiddenType::class, [
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan(['value' => 0]),
                ],
            ]);
    }

    public static function getName()
    {
        return 'thelia_lang_update';
    }
}
