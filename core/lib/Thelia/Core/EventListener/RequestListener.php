<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\EventListener;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpFoundation\Session\SessionManager;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Class RequestListener.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class RequestListener
{
    public function __construct(
        private readonly Translator $translator,
        protected EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly SessionManager $sessionManager,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 128)]
    public function registerValidatorTranslator(RequestEvent $event): void
    {
        $lang = Lang::getDefaultLanguage();
        $request = $event->getRequest();
        if (!$request->get('isApiRoute', false) && $request->hasSession(true)) {
            $lang = $request->getSession()->getLang();
            if (null === $lang) {
                $lang = Lang::getDefaultLanguage();
            }
        }

        $vendorFormDir = THELIA_VENDOR.'symfony'.DS.'form';
        $vendorValidatorDir = THELIA_VENDOR.'symfony'.DS.'validator';

        $this->translator->addResource(
            'xlf',
            \sprintf($vendorFormDir.DS.'Resources'.DS.'translations'.DS.'validators.%s.xlf', $lang->getCode()),
            $lang->getLocale(),
            'validators',
        );
        $this->translator->addResource(
            'xlf',
            \sprintf($vendorValidatorDir.DS.'Resources'.DS.'translations'.DS.'validators.%s.xlf', $lang->getCode()),
            $lang->getLocale(),
            'validators',
        );
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 128)]
    public function rememberMeLoader(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession() || !$request->getSession()->isStarted()) {
            return;
        }

        /** @var Session $session */
        $session = $request->getSession();

        if (null === $session->getCustomerUser()) {
            // Check customer remember me token
            $this->sessionManager->getRememberMeCustomer($request, $session, $this->eventDispatcher);
        }

        // Check admin remember me token
        if (null === $session->getAdminUser()) {
            $this->sessionManager->getRememberMeAdmin($request, $session);
        }
    }

    /**
     * @throws \JsonException
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 128)]
    public function jsonBody(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (
            \count($request->request->all()) > 0
            || !\in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || 'json' !== $request->getFormat($request->headers->get('Content-Type'))
        ) {
            return;
        }

        $content = $request->getContent();

        if (empty($content)) {
            return;
        }

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        if (null === $data) {
            $event->setResponse(
                new JsonResponse(['error' => 'The given data is not a valid json'], Response::HTTP_BAD_REQUEST),
            );

            $event->stopPropagation();

            return;
        }

        if (!\is_array($data)) {
            // This case happens for string like: "Foo", that json_decode returns as valid json
            $data = [$data];
        }

        $request->request = new InputBag($data);
    }

    /**
     * Save the previous URL in session which is based on the referer header or the request, or
     * the _previous_url request attribute, if defined.
     *
     * If the value of _previous_url is "dont-save", the current referrer is not saved.
     */
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: 128)]
    public function registerPreviousUrl(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (
            $request->isXmlHttpRequest()
            || !$request->hasSession(true)
            || !$event->getResponse()->isSuccessful()
            || !$request->getSession()->isStarted()
        ) {
            return;
        }

        $referrer = $request->attributes->get('_previous_url');

        $catalogViews = ['category', 'product'];

        $view = $request->attributes->get('_view', null);

        if (null !== $referrer) {
            // A previous URL (or the keyword 'dont-save') has been specified.
            if ('dont-save' === $referrer) {
                // We should not save the current URL as the previous URL
                $referrer = null;
            }
        } else {
            // The current URL will become the previous URL
            $referrer = $request->getUri();
        }

        // Set previous URL, if defined
        if (null === $referrer) {
            return;
        }

        /** @var Session $session */
        $session = $request->getSession();

        if (ConfigQuery::isMultiDomainActivated()) {
            $components = parse_url($referrer);
            $lang = LangQuery::create()
                ->filterByUrl(\sprintf('%s://%s', $components['scheme'], $components['host']), Criteria::LIKE)
                ->findOne();

            if (null === $lang) {
                return;
            }

            $session->setReturnToUrl($referrer);

            if (\in_array($view, $catalogViews, true)) {
                $session->setReturnToCatalogLastUrl($referrer);
            }

            return;
        }

        if (!str_contains($referrer, $request->getSchemeAndHttpHost())) {
            return;
        }

        $session->setReturnToUrl($referrer);

        if (\in_array($view, $catalogViews, true)) {
            $session->setReturnToCatalogLastUrl($referrer);
        }
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
    public function checkCurrency(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession() || !$request->query->has('currency')) {
            return;
        }

        $currencyToSet = CurrencyQuery::create()
            ->filterByVisible(true)
            ->filterByCode($request->query->get('currency'))
            ->findOne();

        if (null === $currencyToSet) {
            $currencyToSet = Currency::getDefaultCurrency();
        }

        $request->getSession()->setCurrency($currencyToSet);
        $this->eventDispatcher->dispatch(new CurrencyChangeEvent($currencyToSet, $request), TheliaEvents::CHANGE_DEFAULT_CURRENCY);
    }
}
