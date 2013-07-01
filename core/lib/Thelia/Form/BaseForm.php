<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
namespace Thelia\Form;

use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\SessionCsrfProvider;
use Symfony\Component\Validator\Validation;
use Thelia\Form\Extension\NameFormExtension;
use Thelia\Model\ConfigQuery;

abstract class BaseForm {
    /**
     * @var \Symfony\Component\Form\FormFactoryInterface
     */
    protected $form;

    public $name;

    public function __construct(Request $request, $type= "form", $data = array(), $options = array())
    {
        $validator = Validation::createValidator();

        if(!isset($options["attr"]["name"])) {
            $options["attr"]["thelia_name"] = $this->getName();
        }

        $this->form = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(
                new CsrfExtension(
                    new SessionCsrfProvider(
                        $request->getSession(),
                        isset($options["secret"]) ? $options["secret"] : ConfigQuery::read("form.secret", md5(__DIR__))
                    )
                )
            )
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory()
            ->createNamedBuilder($this->getName(), $type, $data, $options);
        ;



            $this->buildForm();
    }

    /**
     * @return \Symfony\Component\Form\Form
     */
    public function getForm()
    {
        return $this->form->getForm();
    }

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->form attribute :
     *
     * $this->form->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    abstract protected function buildForm();

    /**
     * @return string the name of you form. This name must be unique
     */
    abstract public function getName();
}

