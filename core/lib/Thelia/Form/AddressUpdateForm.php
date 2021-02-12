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

/**
 * Class AddressUpdateForm
 * @package Thelia\Form
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AddressUpdateForm extends AddressCreateForm
{
    protected function buildForm()
    {
        parent::buildForm();
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return "thelia_address_update";
    }
}
