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
use Symfony\Component\Validator\Constraints\GreaterThan;

/**
 * Class ContentModificationForm
 * @package Thelia\Form
 * @author manuel raynaud <manu@raynaud.io>
 */
class ContentModificationForm extends ContentCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add("id", HiddenType::class, ["constraints" => [new GreaterThan(['value' => 0])]])
        ;

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(['title', 'locale']);
    }

    public function getName()
    {
        return "thelia_content_modification";
    }
}
