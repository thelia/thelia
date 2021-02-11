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
     * @param  string                $type
     */
    public function createForm(
        string $name,
        $type = FormType::class,
        array $data = [],
        array $options = []
    ): BaseForm;
}
