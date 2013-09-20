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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Form allowing to process a picture
 *
 * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
 *
 * @package Image
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class ImageType extends AbstractType
{
    /**
     * Build a Picture form
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array                $options Form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
//        $builder->add('position');
        $builder->add(
            'title',
            'text',
            array(
                'constraints' => new NotBlank()
            )
        );
        $builder->add(
            'file',
            'file',
            array(
                'constraints' => array(
                    new NotBlank(),
                    new Image(
                        array(
                            'minWidth' => 200,
//                            'maxWidth' => 400,
                            'minHeight' => 200,
//                            'maxHeight' => 400,
                        )
                    )
                )
            )
        );
//        $builder->add('description');
//        $builder->add('chapo');
//        $builder->add('postscriptum');
    }
}
