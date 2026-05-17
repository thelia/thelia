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

namespace BackOfficeDefaultTwigBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

final readonly class AdminLocaleListener
{
    #[AsEventListener(event: 'kernel.request', priority: 20)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/admin') || str_starts_with($path, '/admin/login')) {
            return;
        }

        $session = $request->getSession();

        if (!$session instanceof TheliaSession) {
            return;
        }

        $requestedLang = $request->query->get('lang');

        if (\is_string($requestedLang) && '' !== $requestedLang) {
            $lang = LangQuery::create()->findOneByCode($requestedLang)
                ?? LangQuery::create()->findOneByLocale($requestedLang);

            if ($lang instanceof Lang) {
                $session->setAdminLang($lang);
            }
        }

        $request->setLocale($session->getAdminLang()->getLocale());
    }
}
