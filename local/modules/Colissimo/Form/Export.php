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

namespace Colissimo\Form;

use Colissimo\Colissimo;
use Colissimo\Model\ColissimoQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;


/**
 * Class Export
 * @package Colissimo\Form
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
            ->add('status_id', 'text',[
                'constraints' => [
                    new NotBlank(),
                    new Callback(array(
                        "methods" => array(
                            array($this,
                                "verifyValue")
                        )
                    ))
                ],
                'label' => Translator::getInstance()->trans('Modify status export after export'),
                'label_attr' => [
                    'for' => 'status_id'
                ]
            ]);

        /** @var \Thelia\Model\Order $order */
        foreach ($orders as $order) {
            $this->formBuilder->add("order_".$order->getId(), "checkbox", array(
                    'label'=>$order->getRef(),
                    'label_attr'=>array('for'=>'export_'.$order->getId())
                ));
        }
    }

    public function verifyValue($value, ExecutionContextInterface $context)
    {
        if (!preg_match("#^nochange|processing|sent$#",$value)) {
            $context->addViolation(Translator::getInstance()->trans('select a valid status'));
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