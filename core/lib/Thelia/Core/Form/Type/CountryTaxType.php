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

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Class CountryTaxType
 * @package Thelia\Core\Form\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CountryTaxType extends AbstractTheliaType
{
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->replaceDefaults([
            "type" => "form",
            "cascade_validation" => true,
        ]);
    }


    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("country", "country_id")
            ->add("tax", "collection", array(
                "type" => "tax_id",
                "required" => true,
                "allow_add" => true,
                "cascade_validation" => true,
                "constraints" => array(
                    new Count(["min" => 1])
                )
            ))
        ;
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return "country_tax";
    }

    public function getParent()
    {
        return "collection";
    }
}
