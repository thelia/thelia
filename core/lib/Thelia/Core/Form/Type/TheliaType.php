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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TheliaType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            //'instance'          => false,
            'type'              => false,
            'options'           => false,
        ));

        $resolver->setAllowedTypes(array(
            //'instance'  => array('Thelia\Type\TypeInterface'),
        ));

        $resolver->setAllowedValues(array(
            'type'      => array('text', 'choice'),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, array(
            //'instance'          => $options['instance'],
            'type'              => $options['type'],
            'options'           => $options['options'],
        ));
    }

    public function getParent()
    {
        return 'text';
    }

    public function getName()
    {
        return 'thelia_type';
    }
}
