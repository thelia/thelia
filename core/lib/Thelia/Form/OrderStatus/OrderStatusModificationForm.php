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
namespace Thelia\Form\OrderStatus;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\GreaterThan;

/**
 * Class OrderStatusModificationForm.
 *
 * @author  Gilles Bourgeat <gbourgeat@openstudio.fr>
 *
 * @since 2.4
 */
class OrderStatusModificationForm extends OrderStatusCreationForm
{
    protected function buildForm(): void
    {
        $this->formBuilder->add('id', HiddenType::class, [
            'required' => true,
            'constraints' => [
                new GreaterThan(['value' => 0]),
            ],
        ]);

        parent::buildForm();

        $this->addStandardDescFields();
    }

    public static function getName(): string
    {
        return 'thelia_order_status_modification';
    }
}
