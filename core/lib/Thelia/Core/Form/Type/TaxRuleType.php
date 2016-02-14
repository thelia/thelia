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

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Count;
use Thelia\Core\Form\Type\Field\TaxRuleIdType;

/**
 * Class TaxRuleType
 * @package Thelia\Core\Form\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TaxRuleType extends AbstractTheliaType
{
    /**
     * @var \Thelia\Core\Form\Type\Field\TaxRuleIdType
     */
    protected $taxRuleIdType;

    public function __construct(TaxRuleIdType $taxRuleIdType)
    {
        $this->taxRuleIdType = $taxRuleIdType;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            "cascade_validation" => true,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("default", "checkbox")
            ->add("country", "collection", array(
                "type" => "country_id",
                "allow_add" => true,
                "allow_delete" => true,
                "cascade_validation" => "true",
                "constraints" => array(
                    new Count(["min" => 1]),
                ),
            ))
            ->add("tax", "collection", array(
                "type" => "tax_id",
                "allow_add" => true,
                "allow_delete" => true,
                "cascade_validation" => "true",
                "constraints" => array(
                    new Count(["min" => 1]),
                ),
            ))
            ->add("i18n", "collection", array(
                "type" => "tax_rule_i18n",
                "required" => true,
                "allow_add" => true,
                "cascade_validation" => true,
                "constraints" => array(
                    new Count(["min" => 1]),
                ),
            ))
            ->add("id", "tax_rule_id", array(
                "constraints" => $this->getConstraints($this->taxRuleIdType, "update"),
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
        return "tax_rule";
    }
}
