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

namespace Thelia\Core\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;

/**
 * Class AbstractTheliaType.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * This class adds some tools for simple validation
 */
abstract class AbstractTheliaType extends AbstractType
{
    /**
     * @return array
     *
     * Replaces validation groups in constraints
     */
    protected function replaceGroups($groups, array $constraints): array
    {
        if (!\is_array($groups)) {
            $groups = [$groups];
        }

        /** @var Constraint $constraint */
        foreach ($constraints as &$constraint) {
            $constraint->groups = $groups;
        }

        return $constraints;
    }

    /**
     * @return array
     *
     * Get an array with the type's constraints loaded with groups
     */
    protected function getConstraints(AbstractType $type, string $groups = 'Default'): array
    {
        /**
         * Create a resolver to get the options.
         */
        $nullResolver = new OptionsResolver();
        $type->configureOptions($nullResolver);

        $options = $nullResolver->resolve();

        if (!isset($options['constraints'])) {
            $options['constraints'] = [];
        }

        /*
         * Then replace groups.
         */
        return $this->replaceGroups($groups, $options['constraints']);
    }
}
