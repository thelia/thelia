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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
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
     * Save the previous URL in session which is based on the referer header of the request
     *
     * @param GetResponseEvent $event
     */
    public function registerPreviousUrl(GetResponseEvent $event)
    {

        $request = $event->getRequest();

        // set previous URL
        if (null !== $referer = $request->headers->get('referer')) {

            $session = $request->getSession();

            if (ConfigQuery::read("one_domain_foreach_lang", false) == 1) {

                $components = parse_url($referer);
                $lang = LangQuery::create()
                    ->filterByUrl(sprintf("%s://%s", $components["scheme"], $components["host"]), ModelCriteria::LIKE)
                    ->findOne();

                if (null !== $lang) {
                    $session->setReturnToUrl($referer);
                }

            } else {

                if ( false !== strpos($referer, $request->getSchemeAndHttpHost())) {
                    $session->setReturnToUrl($referer);
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
            KernelEvents::REQUEST => array(
                array("registerValidatorTranslator", 128),
                array("registerPreviousUrl", 128)
            )
        );
    }
}
