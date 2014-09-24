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

namespace Thelia\Core\Template;

use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Form\BaseForm;

/**
 * The parser context is an application-wide context, which stores var-value pairs.
 * Theses pairs are injected in the parser and becomes available to the templates.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ParserContext implements \IteratorAggregate
{
    private $store = array();

    public function __construct(Request $request)
    {
        // Setup basic variables
        $this->set('THELIA_VERSION', Thelia::THELIA_VERSION);
    }

    // -- Error form -----------------------------------------------------------

    /**
     * @param BaseForm $form the errored form
     */
    public function addForm(BaseForm $form)
    {
        $this->set(get_class($form)/*$form->getName()*/, $form);

        return $this;
    }

    public function getForm($name)
    {
        return $this->get($name, null);
    }

    public function setGeneralError($error)
    {
        $this->set('general_error', $error);

        return $this;
    }

    // -- Internal table manipulation ------------------------------------------

    public function set($name, $value)
    {
        $this->store[$name] = $value;

        return $this;
    }

    public function remove($name)
    {
        unset($this->store[$name]);

        return $this;
    }

    public function get($name, $default = null)
    {
        return isset($this->store[$name]) ? $this->store[$name] : $default;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->store);
    }
}
