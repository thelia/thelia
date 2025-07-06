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

namespace Thelia\Core\Form;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Thelia\Form\BaseForm;

/**
 * @deprecated useless factory
 */
interface TheliaFormFactoryInterface
{
    public function createForm(
        string $name,
        $type = FormType::class,
        array $data = [],
        array $options = [],
    ): BaseForm;
}
