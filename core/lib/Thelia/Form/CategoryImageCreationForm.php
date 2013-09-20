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
namespace Thelia\Form;

use Thelia\Core\Translation\Translator;
use Thelia\Form\Type\ImageCategoryType;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Form allowing to process an image collection
 *
 * @package Image
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CategoryImageCreationForm extends BaseForm
{
    /**
     * Allow to build a form
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add('pictures',
                'collection',
                array(
                    'type'   => new ImageCategoryType(),
                    'options'  => array(
                        'required'  => false
                    ),
                    'allow_add' => true,
                    'allow_delete' => true,
                    'by_reference' => false,
                )
            )
            ->add('formSuccessUrl');
    }

    /**
     * Get form name
     *
     * @return string
     */
    public function getName()
    {
        return 'thelia_category_image_creation';
    }
}
