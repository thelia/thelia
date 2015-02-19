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

namespace Thelia\Form;

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
        if (! in_array('url', $exclude)) {
            $this->formBuilder->add(
                'url',
                'text',
                [
                    'required'   => false,
                    'label'      => Translator::getInstance()->trans('Rewriten URL'),
                    'label_attr' => [
                        'for' => 'rewriten_url_field',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('Use the keyword phrase in your URL.'),
                    ]
                ]
            );
        }

        if (! in_array('meta_title', $exclude)) {
            $this->formBuilder->add(
                'meta_title',
                'text',
                [
                    'required'   => false,
                    'label'      => Translator::getInstance()->trans('Page Title'),
                    'label_attr' => [
                        'for' => 'meta_title',
                        'help' => Translator::getInstance()->trans('The HTML TITLE element is the most important element on your web page.'),
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('Make sure that your title is clear, and contains many of the keywords within the page itself.'),
                    ]
                ]
            );
        }

        if (! in_array('meta_description', $exclude)) {
            $this->formBuilder->add(
                'meta_description',
                'textarea',
                [
                    'required'   => false,
                    'label'      => Translator::getInstance()->trans('Meta Description'),
                    'label_attr' => [
                        'for' => 'meta_description',
                        'help' => Translator::getInstance()->trans('Keep the most important part of your description in the first 150-160 characters.'),
                    ],
                    'attr' => [
                        'rows' => 6,
                        'placeholder' => Translator::getInstance()->trans('Make sure it uses keywords found within the page itself.'),
                    ]
                ]
            );
        }

        if (! in_array('meta_keywords', $exclude)) {
            $this->formBuilder->add(
                'meta_keywords',
                'textarea',
                [
                    'required'   => false,
                    'label'      => Translator::getInstance()->trans('Meta Keywords'),
                    'label_attr' => [
                        'for' => 'meta_keywords',
                        'help' => Translator::getInstance()->trans('You don\'t need to use commas or other punctuations.'),
                    ],
                    'attr' => [
                        'rows' => 3,
                        'placeholder' => Translator::getInstance()->trans('Don\'t repeat keywords over and over in a row. Rather, put in keyword phrases.'),
                    ]
                ]
            );
        }
    }
}
