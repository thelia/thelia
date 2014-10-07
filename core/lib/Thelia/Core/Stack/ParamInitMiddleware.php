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

namespace Thelia\Core\Stack;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * Class ParamInitMiddleware
 * @package Thelia\Core\Stack
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class ParamInitMiddleware implements HttpKernelInterface
{
    /**
     * @var HttpKernelInterface
     */
    protected $app;

    protected $urlManager;

    protected $translator;

    protected $eventDispatcher;

    /**
     * @param HttpKernelInterface $app
     */
    public function __construct(HttpKernelInterface $app, URL $urlManager, Translator $translator, EventDispatcherInterface $eventDispatcher)
    {
        $this->app = $app;
        $this->urlManager = $urlManager;
        $this->translator = $translator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @inherited
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        if ($type == HttpKernelInterface::MASTER_REQUEST) {
            $response = $this->initParam($request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        return $this->app->handle($request, $type, $catch);
    }

    protected function initParam(Request $request)
    {
        $lang = $this->detectLang($request);

        if ($lang instanceof Response) {
            return $lang;
        }

        if ($lang) {
            $request->getSession()
                ->setLang($lang)
            ;
        }

        $request->getSession()->setCurrency($this->defineCurrency($request));
        return null;
    }

    protected function defineCurrency(Request $request)
    {
        $currency = null;
        if ($request->query->has("currency")) {
            $currency = CurrencyQuery::create()->findOneByCode($request->query->get("currency"));
            if ($currency) {
                $this->eventDispatcher->dispatch(TheliaEvents::CHANGE_DEFAULT_CURRENCY, new CurrencyChangeEvent($currency, $request));
            }
        } else {
            $currency = $request->getSession()->getCurrency(false);
        }

        if (null === $currency) {
            $currency = Currency::getDefaultCurrency();
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
            $lang = LangQuery::create()->findOneByCode($request->query->get("lang"));

            if (is_null($lang)) {
                return Lang::getDefaultLanguage();
            }

            //if each lang had is own domain, we redirect the user to the good one.
            if (ConfigQuery::read("one_domain_foreach_lang", false) == 1) {
                //if lang domain is different from actuel domain, redirect to the good one
                if (rtrim($lang->getUrl(), "/") != $request->getSchemeAndHttpHost()) {
                    // TODO : search if http status 302 is the good one.
                    $redirect = new RedirectResponse($lang->getUrl(), 302);
                    return $redirect;
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
            if (ConfigQuery::read("one_domain_foreach_lang", false) == 1) {
                //find lang with domain
                return LangQuery::create()->filterByUrl($request->getSchemeAndHttpHost(), ModelCriteria::LIKE)->findOne();
            }

            //find default lang
            return Lang::getDefaultLanguage();
        }
    }
}
