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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CurrencyQuery;

class CurrencyCreationForm extends BaseForm
{
    protected function buildForm($change_mode = false)
    {
        $this->formBuilder
            ->add("name", "text", [
                "constraints" => [
                    new NotBlank(),
                ],
                "label"       => Translator::getInstance()->trans('Name *'),
                "label_attr"  => [
                    "for" => "name",
                ]
            ])
            ->add("locale", "text", [
                "constraints" => [
                    new NotBlank(),
                ]
            ])
            ->add("symbol", "text", [
                "constraints" => [
                    new NotBlank(),
                ],
                "label"       => Translator::getInstance()->trans('Symbol *'),
                "label_attr"  => [
                    "for" => "symbol",
                ]
            ])
            ->add("format", "text", [
                "constraints" => [
                    new NotBlank(),
                ],
                "label"       => Translator::getInstance()->trans('Format *'),
                "label_attr"  => [
                    "for" => "format",
                ]
            ])
            ->add("rate", "text", [
                "constraints" => [
                    new NotBlank(),
                ],
                "label"       => Translator::getInstance()->trans('Rate from &euro; *'),
                "label_attr"  => [
                    "for" => "rate",
                ]
            ])
            ->add("code", "text", [
                "constraints" => [
                    new NotBlank(),
                ],
                "label"       => Translator::getInstance()->trans('ISO 4217 code *'),
                "label_attr"  => [
                    "for" => "iso_4217_code",
                ]
            ])
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
            $context->addViolation(
                Translator::getInstance()->trans(
                    'A currency with code "%name" already exists.',
                    ['%name' => $value]
                )
            );
        }
    }
}
