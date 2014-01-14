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
namespace Thelia\Core;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session;

use Thelia\Core\Event\Currency\CurrencyEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model;

/**
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class TheliaHttpKernel extends HttpKernel
{
    protected static $session;

    protected $container;

    public function __construct(EventDispatcherInterface $dispatcher, ContainerInterface $container, ControllerResolverInterface $controllerResolver)
    {
        parent::__construct($dispatcher, $controllerResolver);

        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Handles a Request to convert it to a Response.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param Request $request A Request instance
     * @param integer $type    The type of the request
     *                          (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param Boolean $catch Whether to catch exceptions or not
     *
     * @return Response A Response instance
     *
     * @throws \Exception When an Exception occurs during processing
     *
     * @api
     *
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        //$request->headers->set('X-Php-Ob-Level', ob_get_level());
        if ($type == HttpKernelInterface::MASTER_REQUEST) {
            $request = $this->initSession($request);
            $this->initParam($request);
        }

        $this->container->enterScope('request');
        $this->container->set('request', $request, 'request');

        try {
            $response = parent::handle($request, $type, $catch);
        } catch (\Exception $e) {
            $this->container->leaveScope('request');

            throw $e;
        }

        $this->container->leaveScope('request');

        return $response;
    }

    /*

     * @TODO define here all needed parameters like locale, currency, etc :
     *
     *
     * LOCALE :
     * define locale with this order :
     * 1) locale define into plugins
     * 2) locale paramter in request
     * 3) current locale <-> locale
     * 4) locale in session
     * 5) default locale
     *
     * $lang déjà défini par un plugin
     * 2) paramètre 'lang' dans l'URL courante
     * 3) Correspondance URL courante <-> langue
     * 4) Langue précédemment stockée en session
     * 5) Defaut
     * put this locale into session with keyname "locale"
     *
     *
     *
     *
     *
     */
    protected function initParam(Request $request)
    {
        // Ensure an instaciation of URL service, which is accessed as a pseudo-singleton
        // in the rest of the application.
        // See Thelia\Tools\URL class.
        $this->container->get('thelia.url.manager');

        // Same thing for the Translator service.
        $this->container->get('thelia.translator');

        $lang = $this->detectLang($request);

        if ($lang) {
            $request->getSession()
                ->setLang($lang)
            ;
        }

        $request->getSession()->setCurrency($this->defineCurrency($request));
    }

    protected function defineCurrency(Request $request)
    {
        $currency = null;
        if ($request->query->has("currency")) {
            $currency = Model\CurrencyQuery::create()->findOneByCode($request->query->get("currency"));
            if ($currency) {
                $this->container->get("event_dispatcher")->dispatch(TheliaEvents::CHANGE_DEFAULT_CURRENCY, new CurrencyEvent($currency));
            }
        } else {
            $currency = $request->getSession()->getCurrency(false);
        }

        if (null === $currency) {
            $currency = Model\Currency::getDefaultCurrency();
        }

        return $currency;
    }

    /**
     * @param  Request                 $request
     * @return null|\Thelia\Model\Lang
     */
    protected function detectLang(Request $request)
    {
        $lang = null;

        //first priority => lang parameter present in request (get or post)
        if ($request->query->has("lang")) {
            $lang = Model\LangQuery::create()->findOneByCode($request->query->get("lang"));

            if (is_null($lang)) {
                return Model\Lang::getDefaultLanguage();
            }

            //if each lang had is own domain, we redirect the user to the good one.
            if (Model\ConfigQuery::read("one_domain_foreach_lang", false) == 1) {
                //if lang domain is different from actuel domain, redirect to the good one
                if (rtrim($lang->getUrl(), "/") != $request->getSchemeAndHttpHost()) {
                    // TODO : search if http status 302 is the good one.
                    $redirect = new RedirectResponse($lang->getUrl(), 302);
                    $redirect->send();
                    exit;
                } else {
                    //the user is actually on the good domain, nothing to change
                    return null;
                }
            } else {
                //one domain for all languages, the lang is set into session
                return $lang;
            }
        }

        //check if lang is not defined. If not we have to search the good one.
        if (null === $request->getSession()->getLang(false)) {

            if (Model\ConfigQuery::read("one_domain_foreach_lang", false) == 1) {
                //find lang with domain
                return Model\LangQuery::create()->filterByUrl($request->getSchemeAndHttpHost(), ModelCriteria::LIKE)->findOne();
            }

            //find default lang
            return Model\Lang::getDefaultLanguage();
        }
    }

    public function initSession(Request $request)
    {
        if (null === self::$session) {
            $storage = new Session\Storage\NativeSessionStorage();

            if (Model\ConfigQuery::read("session_config.default")) {
                $storage->setSaveHandler(new Session\Storage\Handler\NativeFileSessionHandler(Model\ConfigQuery::read("session_config.save_path", THELIA_ROOT . '/local/session/')));
            } else {
                $handlerString = Model\ConfigQuery::read("session_config.handlers", 'Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler');

                $handler = new $handlerString;

                $storage->setSaveHandler($handler);
            }

            if (Model\ConfigQuery::read("session_config.config", null)) {
                $storage->setOptions(json_decode(Model\ConfigQuery::read("session_config.config")));
            }

            self::$session = $session = new \Thelia\Core\HttpFoundation\Session\Session($storage);
        } else {
            $session = self::$session;
        }

        $session->start();
        $request->setSession($session);

        return $request;
    }
}
