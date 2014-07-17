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
use Thelia\Core\Translation\Translator;

/**
 * Class ImportForm
 * @package Thelia\Form
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportForm extends BaseForm
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
        $this->formBuilder->add("file_upload", "file", array(
            "label" => Translator::getInstance()->trans("File to upload"),
            "label_attr" => ["for" => "file_to_upload"],
            "required" => true,
        ));
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_import";
    }

} 