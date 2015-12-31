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

class TaxRuleCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm($change_mode = false)
    {
        $this->formBuilder
            ->add("locale", "hidden", array(
                "constraints" => array(new NotBlank()),
            ))
        ;

        $this->addStandardDescFields(array('postscriptum', 'chapo', 'locale'));
    }

    public function getName()
    {
        return "thelia_tax_rule_creation";
    }
}
