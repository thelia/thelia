<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/
namespace Thelia\Core\Form;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Thelia\Form\BaseForm;

/**
 * Class TheliaFormFactory
 * @package Thelia\Core\Form
 * @author Benjamin Perche <benjamin@thelia.net>
 */
interface TheliaFormFactoryInterface
{
    /**
     * @param  string                $name
     * @param  string                $type
     * @param  array                 $data
     * @param  array                 $options
     * @return BaseForm
     */
    public function createForm(
        string $name,
        $type = FormType::class,
        array $data = array(),
        array $options = array()
    ): BaseForm;
}
