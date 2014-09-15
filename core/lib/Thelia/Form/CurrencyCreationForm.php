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

use Symfony\Component\Validator\Constraints;
use Thelia\Model\CurrencyQuery;
use Symfony\Component\Validator\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class CurrencyCreationForm extends BaseForm
{
    protected function buildForm($change_mode = false)
    {
        $this->formBuilder
            ->add("name"   , "text"  , array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans('Name *'),
                "label_attr" => array(
                    "for" => "name"
                ))
            )
            ->add("locale" , "text"  , array(
                "constraints" => array(
                    new NotBlank()
                ))
            )
            ->add("symbol" , "text"  , array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans('Symbol *'),
                "label_attr" => array(
                    "for" => "symbol"
                ))
            )
            ->add("rate"   , "text"  , array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans('Rate from &euro; *'),
                "label_attr" => array(
                    "for" => "rate"
                ))
            )
            ->add("code"   , "text"  , array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans('ISO 4217 code *'),
                "label_attr" => array(
                    "for" => "iso_4217_code"
                ))
            )
        ;
    }

    public function getName()
    {
        return "thelia_currency_creation";
    }

    public function checkDuplicateCode($value, ExecutionContextInterface $context)
    {
        $currency = CurrencyQuery::create()->findOneByCode($value);

        if ($currency) {
            $context->addViolation(Translator::getInstance()->trans('A currency with code "%name" already exists.', array('%name' => $value)));
        }
    }

}
