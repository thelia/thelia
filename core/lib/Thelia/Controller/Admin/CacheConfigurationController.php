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


namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Cache\CacheFactory;
use Thelia\Core\Event\Cache\TCacheUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\NotImplementedException;
use Thelia\Form\CacheConfigurationForm;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;


/**
 * Class CacheConfigurationController
 * @package Thelia\Controller\Admin
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CacheConfigurationController extends BaseAdminController
{


    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, array(), AccessManager::VIEW)) {
            return $response;
        }

        // Hydrate the store configuration form
        $cacheConfigForm = new CacheConfigurationForm($this->getRequest(), 'form', array(
            'enabled' => (bool)ConfigQuery::read(CacheFactory::CONFIG_CACHE_ENABLED, false),
            'driver'  => ConfigQuery::read(CacheFactory::CONFIG_CACHE_DRIVER, CacheFactory::DEFAULT_CACHE_DRIVER),
        ));
        $this->getParserContext()->addForm($cacheConfigForm);

        return $this->renderTemplate();
    }

    protected function renderTemplate()
    {
        return $this->render('config-cache');
    }

    public function testAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $config = $this->getRequest()->request->get("config", null);

        //Tlog::getInstance()->debug(" GU " . print_r($config, true));

        if (null !== $config) {
            $message = $this->testConfig($config);
        } else {
            $message = Translator::getInstance()->trans("The configuration is not valid.");
        }

        return $this->jsonResponse(json_encode(array(
                "success" => (null === $message),
                "message" => (null !== $message)
                        ? $message
                        : Translator::getInstance()->trans("The configuration is valid.")
            )
        ));

    }


    protected function testConfig(array $config)
    {
        $message = null;
        try {
            $cache = CacheFactory::getNewInstance($config);
            $cache->save("test", "test");
            $test = $cache->fetch("test");
            if ("test" !== $test) {
                $message = Translator::getInstance()->trans("The initialization of the driver is ok but it could not fetch the test value !");
            }
            $cache->delete("test");

        } catch (\Exception $ex) {
            $message = $ex->getMessage();
        }

        return $message;
    }


    public function saveAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $error_message = false;
        $cacheForm     = new CacheConfigurationForm($this->getRequest());

        try {

            $form = $this->validateForm($cacheForm);

            $event = $this->createModificationEvent($form);

            $this->dispatch(TheliaEvents::TCACHE_UPDATE, $event);

            $this->redirect($cacheForm->getSuccessUrl());

        } catch (\Exception $ex) {
            $error_message = $ex->getMessage();
        }

        // error
        $this->setupFormErrorContext(
            $this->getTranslator()->trans("Cache configuration failed."),
            $error_message,
            $cacheForm,
            $ex
        );

        return $this->renderTemplate();
    }


    protected function createModificationEvent($form)
    {
        $event = new TCacheUpdateEvent();
        $data  = $form->getData();

        foreach ($data as $key => $value) {
            if (!in_array($key, array('success_url', 'error_message'))) {
                $event->__set("tcache_$key", $value);
            }
        }

        return $event;

    }


    public function statsAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $stats = CacheFactory::getInstance()->getStats();

        return $this->render("includes/config-cache-stats", array("stats" => $stats));
    }


    public function flushAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $ret = CacheFactory::getInstance()->deleteAll();

        return $this->jsonResponse(json_encode(array(
            "success" => $ret,
            "message" => $ret ?
                    Translator::getInstance()->trans("The cache has been flushed.") :
                    Translator::getInstance()->trans("The cache can't be flushed.")
        )));
    }

} 