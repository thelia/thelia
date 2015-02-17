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
     * @return \Thelia\Form\BaseForm
     */
    public function createForm($name, $type = "form", array $data = array(), array $options = array());
}
