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

namespace Thelia\Core\Template\Smarty\Plugins;

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Element\FlashMessage as FlashMessageBag;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Translation\Translator;

/**
 * Plugin for smarty defining blocks allowing to get flash message
 * A flash message is a variable, array, object stored in session under the flashMessage key
 * ex $SESSION['flashMessage']['myType']
 *
 * blocks :
 *
 * ```
 * {flash type="myType"}
 *     <div class="alert alert-success">{$MESSAGE}</div>
 * {/flash}
 * ```
 * Class Form
 *
 * @package Thelia\Core\Template\Smarty\Plugins
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FlashMessage extends AbstractSmartyPlugin
{
    /** @var Request Request service */
    protected $request;

    /** @var FlashMessageBag $results */
    protected $results;

    /** @var Translator */
    protected $translator;

    /**
     * Constructor
     *
     * @param Request $request Request service
     */
    public function __construct(Request $request, Translator $translator)
    {
        $this->request = $request;
        $this->translator = $translator;
    }

    /**
     * Process the count function: executes a loop and return the number of items found
     *
     * @param array $params parameters array
     * @param \Smarty_Internal_Template $template
     *
     * @return int                       the item count
     * @throws \InvalidArgumentException if a parameter is missing
     *
     */
    public function hasFlashMessage(
        $params,
        /** @noinspection PhpUnusedParameterInspection */
        $template
    ) {
        $type = $this->getParam($params, 'type', null);

        if (null == $type) {
            throw new \InvalidArgumentException(
                $this->translator->trans("Missing 'type' parameter in {hasflash} function arguments")
            );
        }

        return $this->request->getSession()->getFlashBag()->has($type);
    }

    /**
     * Get FlashMessage
     * And clean session from this key
     *
     * @param array $params Block parameters
     * @param mixed $content Block content
     * @param \Smarty_Internal_Template $template Template
     * @param bool $repeat Control how many times
     *                                            the block is displayed
     *
     * @return mixed
     */
    public function getFlashMessage($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        $type = $this->getParam($params, 'type', false);

        if (null === $content) {

            $this->results = new FlashMessageBag();

            if (false === $type) {
                $this->results->addAll($this->request->getSession()->getFlashBag()->all());
            } else {
                $this->results->add(
                    $type,
                    $this->request->getSession()->getFlashBag()->get($type, [])
                );
            }

            if ($this->results->isEmpty()) {
                $repeat = false;
            }

        } else {
            $this->results->next();
        }

        if ($this->results->valid()) {
            $message = $this->results->current();
            $template->assign("TYPE", $message["type"]);
            $template->assign("MESSAGE", $message["message"]);

            $repeat = true;
        }

        if ($content !== null) {
            if ($this->results->isEmpty()) {
                $content = "";
            }

            return $content;
        }

        return '';
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor("function", "hasflash", $this, "hasFlashMessage"),
            new SmartyPluginDescriptor("block", "flash", $this, "getFlashMessage")
        ];
    }
}
