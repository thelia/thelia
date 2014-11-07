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

use Thelia\Cache\CacheFactory;
use Thelia\Cache\Driver\CacheDriverInterface;
use Thelia\Core\Event\Cache\TCacheUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\CacheConfigurationForm;

/**
 * Class CacheConfigurationController
 * @package Thelia\Controller\Admin
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CacheConfigurationController extends BaseAdminController
{
    /** @var  CacheFactory */
    protected $cacheFactory;

    /** @var  CacheDriverInterface */
    protected $cache;

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, [], AccessManager::VIEW)) {
            return $response;
        }

        // Hydrate the store configuration form
        $cacheConfigForm = new CacheConfigurationForm(
            $this->getRequest(),
            'form'
        );
        $this->getParserContext()->addForm($cacheConfigForm);

        return $this->renderTemplate();
    }

    protected function renderTemplate()
    {
        return $this->render('config-cache');
    }

    public function testAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, [], AccessManager::UPDATE)) {
            return $response;
        }

        $config = $this->getRequest()->request->get("config", null);

        //Tlog::getInstance()->debug(" GU " . print_r($config, true));

        if (is_array($config) && array_key_exists('driver', $config)) {
            $message = $this->testConfig($config['driver'], $config);
        } else {
            $message = $this->getTranslator()->trans("The configuration is not valid.");
        }

        return $this->jsonResponse(
            json_encode(
                [
                    "success" => (null === $message),
                    "message" => (null !== $message)
                        ? $message
                        : $this->getTranslator()->trans("The configuration is valid.")
                ]
            )
        );
    }

    protected function testConfig($driver, array $config)
    {
        $message = null;
        try {
            $cache = $this->getCacheFactory()->get($driver, $config, false);
            $cache->save("test", "test");
            $test = $cache->fetch("test");
            if ("test" !== $test) {
                $message = $this->getTranslator()->trans("The initialization of the driver is ok but it could not fetch the test value !");
            }
            $cache->delete("test");
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
        }

        return $message;
    }

    public function saveAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, [], AccessManager::UPDATE)) {
            return $response;
        }

        $error_message = false;
        $cacheForm = new CacheConfigurationForm($this->getRequest());

        try {

            $form = $this->validateForm($cacheForm);

            $event = $this->createModificationEvent($form);

            $this->dispatch(TheliaEvents::TCACHE_UPDATE, $event);

            return $this->generateRedirect($cacheForm->getSuccessUrl());
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
        $data = $form->getData();

        foreach ($data as $key => $value) {
            if (!in_array($key, ['success_url', 'error_message'])) {
                $event->__set("tcache_$key", $value);
            }
        }

        return $event;
    }


    public function statsAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, [], AccessManager::VIEW)) {
            return $response;
        }

        $stats = $this->getCache()->getStats();

        return $this->render("includes/config-cache-stats", ["stats" => $stats]);
    }


    public function flushAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, [], AccessManager::UPDATE)) {
            return $response;
        }

        $ret = $this->getCache()->flush();

        return $this->jsonResponse(
            json_encode(
                [
                    "success" => $ret,
                    "message" => $ret ?
                        $this->getTranslator()->trans("The cache has been flushed.") :
                        $this->getTranslator()->trans("The cache can't be flushed.")
                ]
            )
        );
    }

    /**
     * @return CacheDriverInterface
     */
    protected function getCache()
    {
        return $this->container->get('thelia.cache');
    }

    /**
     * @return CacheFactory
     */
    protected function getCacheFactory()
    {
        return $this->container->get('thelia.cache.factory');
    }
}
