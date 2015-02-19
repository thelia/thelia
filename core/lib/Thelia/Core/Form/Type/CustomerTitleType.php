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
use Symfony\Component\Validator\Constraints\Count;
use Thelia\Core\Form\Type\Field\CustomerTitleIdType;

/**
 * Class CustomerTitleType
 * @package Thelia\Core\Form\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CustomerTitleType extends AbstractTheliaType
{
    /**
     * @var CustomerTitleIdType
     */
    protected $customerTitleIdType;

    public function __construct(CustomerTitleIdType $customerTitleIdType)
    {
        $this->customerTitleIdType = $customerTitleIdType;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("i18n", "collection", array(
                "type" => "customer_title_i18n",
                "allow_add" => true,
                "required" => true,
                "cascade_validation" => true,
                "constraints" => array(
                    new Count(["min" => 1]),
                ),
            ))
            ->add("default", "checkbox")
            ->add("title_id", "customer_title_id", array(
                "constraints" => $this->getConstraints($this->customerTitleIdType, "update"),
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
        return "customer_title";
    }
}
