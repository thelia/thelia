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
use Thelia\Cache\TCache;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\NotImplementedException;
use Thelia\Form\CacheConfigurationForm;
use Thelia\Model\ConfigQuery;


/**
 * Class CacheConfigurationController
 * @package Thelia\Controller\Admin
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CacheConfigurationController extends BaseAdminController {


    protected function renderTemplate()
    {
        return $this->render('config-cache');
    }


    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, array(), AccessManager::VIEW)) {
            return $response;
        }

        // Hydrate the store configuration form
        $cacheConfigForm = new CacheConfigurationForm($this->getRequest(), 'form', array(
            'enabled'             => ConfigQuery::read(TCache::CONFIG_CACHE_ENABLED),
            'driver'              => ConfigQuery::read(TCache::CONFIG_CACHE_DRIVER),
        ));
        $this->getParserContext()->addForm($cacheConfigForm);

        return $this->renderTemplate();
    }

    public function testAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $config = $this->getRequest()->request->get("config", null);

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
            $cache = TCache::getNewInstance($config);
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
        throw New NotImplementedException("Not yet implemented");
    }

    public function statsAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CACHE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $stats = TCache::getInstance()->getStats();

        return $this->render("includes/config-cache-stats", array("stats" => $stats));
    }

    public function flushAction()
    {
        $ret = TCache::getInstance()->deleteAll();
        return $this->jsonResponse(json_encode(array("success" => $ret)));
    }

} 