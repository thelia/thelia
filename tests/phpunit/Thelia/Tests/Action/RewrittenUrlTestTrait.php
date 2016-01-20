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

namespace Thelia\Tests\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\ProductQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Rewriting\RewritingResolver;

/**
 * Class RewrittenUrlTestTrait
 * @package Thelia\Tests\Action

 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method EventDispatcherInterface getMockEventDispatcher()
 */
trait RewrittenUrlTestTrait
{
    abstract public function getUpdateEvent(&$object);
    abstract public function getUpdateSeoEvent(&$object);
    abstract public function processUpdateAction($event);
    abstract public function processUpdateSeoAction($event);

    /**
     * @expectedException \Thelia\Form\Exception\FormValidationException
     * @expectedExceptionCode 100
     */
    public function testUpdateExistingUrl()
    {
        $object = null;
        $event = $this->getUpdateSeoEvent($object);

        /* get an existing url */
        $existingUrl = RewritingUrlQuery::create()
            ->filterByViewId($object->getId(), Criteria::NOT_EQUAL)
            ->filterByRedirected(null)
            ->filterByView(ConfigQuery::getObsoleteRewrittenUrlView(), Criteria::NOT_EQUAL)
            ->findOne();

        if (null === $existingUrl) {
            $this->fail('use fixtures before launching test, there is not enough rewritten url');
        }

        $event->setUrl($existingUrl->getUrl());

        $this->processUpdateSeoAction($event);
    }

    public function testUpdateUrl()
    {
        $object = null;
        $event = $this->getUpdateSeoEvent($object);

        $currentUrl = $object->getRewrittenUrl($object->getLocale());

        /* get a brand new URL */
        $exist = true;
        while (true === $exist) {
            $newUrl = md5(rand(1, 999999)) . ".html";
            try {
                new RewritingResolver($newUrl);
            } catch (UrlRewritingException $e) {
                if ($e->getCode() === UrlRewritingException::URL_NOT_FOUND) {
                    /* It's all good if URL is not found */
                    $exist = false;
                } else {
                    throw $e;
                }
            }
        }

        $event->setUrl($newUrl);

        $updatedObject = $this->processUpdateSeoAction($event);

        /* new URL is updated */
        $this->assertEquals($newUrl, $updatedObject->getRewrittenUrl($object->getLocale()));

        /* old url must be redirected to the new one */
        $newUrlEntry = RewritingUrlQuery::create()->findOneByUrl($newUrl);
        $oldUrlEntry = RewritingUrlQuery::create()->findOneByUrl($currentUrl);

        $this->assertEquals($oldUrlEntry->getRedirected(), $newUrlEntry->getId());

        /* we can reassign old Url to another object */
        $aRandomProduct = ProductQuery::create()
            ->filterById($object->getId(), Criteria::NOT_EQUAL)
            ->findOne();

        $failReassign = true;
        try {
            $aRandomProduct->setRewrittenUrl($aRandomProduct->getLocale(), $currentUrl);
            $failReassign = false;
        } catch (\Exception $e) {
        }

        $this->assertFalse($failReassign);
    }
}
