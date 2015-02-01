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

namespace Thelia\Core\EventListener;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;

/**
 * Class RequestListener
 * @package Thelia\Core\EventListener
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class RequestListener implements EventSubscriberInterface
{
    protected $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function registerValidatorTranslator(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $lang = $request->getSession()->getLang();
        $vendorDir = THELIA_ROOT . "/core/vendor";
        $vendorFormDir = $vendorDir . '/symfony/form/Symfony/Component/Form';
        $vendorValidatorDir =
            $vendorDir . '/symfony/validator/Symfony/Component/Validator';

        $this->translator->addResource(
            'xlf',
            sprintf($vendorFormDir . '/Resources/translations/validators.%s.xlf', $lang->getCode()),
            $lang->getLocale(),
            'validators'
        );
        $this->translator->addResource(
            'xlf',
            sprintf($vendorValidatorDir . '/Resources/translations/validators.%s.xlf', $lang->getCode()),
            $lang->getLocale(),
            'validators'
        );
    }

    /**
     * Save the previous URL in session which is based on the referer header or the request, or
     * the _previous_url request attribute, if defined.
     *
     * If the value of _previous_url is "dont-save", the current referrer is not saved.
     *
     * @param \Symfony\Component\HttpKernel\Event\PostResponseEvent $event
     */
    public function registerPreviousUrl(PostResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->isXmlHttpRequest()) {
            $referrer = $request->attributes->get('_previous_url', null);

            if (null !== $referrer) {
                // A previous URL (or the keyword 'dont-save') has been specified.
                if ('dont-save' == $referrer) {
                    // We should not save the current URL as the previous URL
                    $referrer = null;
                }
            } else {
                // The current URL will become the previous URL
                $referrer = $request->getUri();
            }

            // Set previous URL, if defined
            if (null !== $referrer) {
                $session = $request->getSession();

                if (ConfigQuery::read("one_domain_foreach_lang", false) == 1) {
                    $components = parse_url($referrer);
                    $lang = LangQuery::create()
                        ->filterByUrl(sprintf("%s://%s", $components["scheme"], $components["host"]), ModelCriteria::LIKE)
                        ->findOne();

                    if (null !== $lang) {
                        $session->setReturnToUrl($referrer);
                    }
                } else {
                    if (false !== strpos($referrer, $request->getSchemeAndHttpHost())) {
                        $session->setReturnToUrl($referrer);
                    }
                }
            }
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => [
                ["registerValidatorTranslator", 128]
            ],
            KernelEvents::TERMINATE => [
                ["registerPreviousUrl", 128]
            ]
        );
    }
}
