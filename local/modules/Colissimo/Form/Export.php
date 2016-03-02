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

namespace Colissimo\Form;

use Colissimo\Colissimo;
use Colissimo\Model\ColissimoQuery;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class Export
 * @package Colissimo\Form
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Export extends BaseForm
{

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     * $this->formBuilder->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    protected function buildForm()
    {
        $orders = ColissimoQuery::getOrders()
            ->find();

        $this->formBuilder
            ->add(
                'status_id',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                        new Callback(
                            array("methods" => array(array($this, "verifyValue")))
                        )
                    ],
                    'label' => Translator::getInstance()->trans(
                        'Modify status export after export',
                        [],
                        Colissimo::DOMAIN_NAME
                    ),
                    'label_attr' => [
                        'for' => 'status_id'
                    ]
                ]
            );

        /** @var \Thelia\Model\Order $order */
        foreach ($orders as $order) {
            $this->formBuilder
                ->add(
                    "order_".$order->getId(),
                    "checkbox",
                    array(
                        'label'=>$order->getRef(),
                        'label_attr'=>array(
                            'for'=>'export_'.$order->getId()
                        )
                    )
                )
                ->add(
                    "order_nb_pkg_".$order->getId(),
                    'number'
                )
                ->add(
                    "order_weight_".$order->getId(),
                    'number'
                );
        }
    }

    public function verifyValue($value, ExecutionContextInterface $context)
    {
        if (!preg_match("#^nochange|processing|sent$#", $value)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'select a valid status',
                    [],
                    Colissimo::DOMAIN_NAME
                )
            );
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "colissimo_export";
    }
}
