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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class CustomerTitleI18nType
 * @package Thelia\Core\Form\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CustomerTitleI18nType extends AbstractTheliaType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            "cascade_validation" => true,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("locale", "text", array(
                "required" => true,
                "constraints" => array(
                    new NotBlank(),
                ),
            ))
            ->add("short", "text", array(
                "required" => false,
                "constraints" => array(
                    new Length(["max" => 10]),
                ),
            ))
            ->add("long", "text", array(
                "required" => false,
                "constraints" => array(
                    new Length(["max" => 45]),
                ),
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
        return "customer_title_i18n";
    }
}
