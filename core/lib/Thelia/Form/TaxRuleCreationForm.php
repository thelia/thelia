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

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaxRuleCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add("locale", HiddenType::class, [
                "constraints" => [new NotBlank()],
            ])
        ;

        $this->addStandardDescFields(['postscriptum', 'chapo', 'locale']);
    }

    public function getName()
    {
        return "thelia_tax_rule_creation";
    }
}
