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

namespace BackOfficeDefaultTwigBundle\Service\Admin;

use Propel\Runtime\Event\ActiveRecordEvent;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormInterface;

/**
 * Copy form field values into a Propel ActiveRecord event using camelCase setters,
 * extracted from the legacy AbstractCrudController so back-office controllers stay flat.
 */
final readonly class AdminEventBinder
{
    public function bind(ActiveRecordEvent $event, FormInterface $form): void
    {
        foreach ($form as $field) {
            $fieldName = $field->getName();
            $setter = \sprintf('set%s', Container::camelize($fieldName));

            if (method_exists($event, $setter)) {
                $getter = \sprintf('get%s', Container::camelize($fieldName));

                if (method_exists($event, $getter) && null !== $event->{$getter}()) {
                    continue;
                }

                $event->{$setter}($field->getData());

                continue;
            }

            $event->{$fieldName} = $field->getData();
        }
    }
}
