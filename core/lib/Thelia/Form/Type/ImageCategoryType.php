<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Thelia\Form\Type\ImageType;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Form allowing to process a category picture
 *
 * @package Image
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ImageCategoryType extends ImageType
{
    /**
     * Build a Picture form
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array                $options Form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('category_id', 'integer');

        parent::buildForm($builder, $options);
    }

    /**
     * Set default options
     * Map the form to the given Model
     *
     * @param OptionsResolverInterface $resolver Option resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Thelia\Model\CategoryImage'
            )
        );
    }

    /**
     * Get form name
     *
     * @return string
     */
    public function getName()
    {
        return 'thelia_category_picture_creation_type';
    }
}
