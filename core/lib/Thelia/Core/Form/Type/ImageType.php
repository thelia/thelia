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

namespace Thelia\Core\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class ImageType.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImageType extends AbstractTheliaType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('i18n', 'collection', [
                'type' => 'standard_fields',
                'allow_add' => true,
                'allow_delete' => true,
                'cascade_validation' => true,
            ])
        ;
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'image';
    }
}
