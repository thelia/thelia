<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AbstractTheliaType
 * @package Thelia\Core\Form\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * This class adds some tools for simple validation
 */
abstract class AbstractTheliaType extends AbstractType
{
    /**
     * @param $groups
     * @param  array $constraints
     * @return array
     *
     * Replaces validation groups in constraints
     */
    protected function replaceGroups($groups, array $constraints)
    {
        if (!is_array($groups)) {
            $groups = [$groups];
        }

        /** @var \Symfony\Component\Validator\Constraint $constraint */
        foreach ($constraints as &$constraint) {
            $constraint->groups = $groups;
        }

        return $constraints;
    }

    /**
     * @param  AbstractType $type
     * @param  string       $groups
     * @return array
     *
     * Get an array with the type's constraints loaded with groups
     */
    protected function getConstraints(AbstractType $type, $groups = "Default")
    {
        /**
         * Create a resolver to get the options
         */
        $nullResolver = new OptionsResolver();
        $type->configureOptions($nullResolver);

        $options = $nullResolver->resolve();

        if (!isset($options["constraints"])) {
            $options["constraints"] = array();
        }

        /**
         * Then replace groups
         */
        $constraints = $this->replaceGroups($groups, $options["constraints"]);

        return $constraints;
    }
}
