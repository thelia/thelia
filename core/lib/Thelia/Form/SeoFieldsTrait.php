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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

/**
 * A trait to add standard localized description fields to a form.
 *
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
trait SeoFieldsTrait
{
    /**
     * Add seo meta title, meta description and meta keywords fields
     *
     * @param array $exclude name of the fields that should not be added to the form
     */
    protected function addSeoFields($exclude = array())
    {

        if (! in_array('url', $exclude))
            $this->formBuilder
                ->add('url', 'text', array(
                    'label'       => Translator::getInstance()->trans('Rewriten URL'),
                    'label_attr' => array(
                        'for' => 'rewriten_url_field'
                    ),
                    'required' => false
                )
            );

        if (! in_array('meta_title', $exclude))
            $this->formBuilder
                ->add('meta_title', 'text', array(
                    'constraints' => array(
                        new NotBlank()
                    ),
                    'label' => Translator::getInstance()->trans('Page Title'),
                    'label_attr' => array(
                        'for' => 'meta_title'
                    )
                )
            );

        if (! in_array('meta_description', $exclude))
            $this->formBuilder
                ->add('meta_description', 'text', array(
                    'label' => Translator::getInstance()->trans('Meta Description'),
                    'label_attr' => array(
                        'for' => 'meta_description'
                    ),
                    'required' => false
                )
            );

        if (! in_array('meta_keywords', $exclude))
            $this->formBuilder
                ->add('meta_keywords', 'text', array(
                        'label' => Translator::getInstance()->trans('Meta Keywords'),
                        'label_attr' => array(
                            'for' => 'meta_keywords'
                        ),
                        'required' => false
                    )
                );
     }
}
