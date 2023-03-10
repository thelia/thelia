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

namespace HookNavigation\Form;

use HookNavigation\HookNavigation;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Thelia\Form\BaseForm;

/**
 * Class HookNavigationConfigForm.
 *
 * @author Etienne PERRIERE <eperriere@openstudio.fr> - OpenStudio
 */
class HookNavigationConfigForm extends BaseForm
{
    public static function getName()
    {
        return 'hooknavigation_configuration';
    }

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'footer_body_folder_id',
                NumberType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Folder in footer body', [], HookNavigation::MESSAGE_DOMAIN),
                ]
            )
            ->add(
                'footer_bottom_folder_id',
                NumberType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Folder in footer bottom', [], HookNavigation::MESSAGE_DOMAIN),
                ]
            );
    }
}
