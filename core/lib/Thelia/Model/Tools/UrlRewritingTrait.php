<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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

namespace Thelia\Model\Tools;

use Thelia\Core\Event\GenerateRewrittenUrlEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\RewritingArgumentQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Model\RewritingUrl;
use Thelia\Rewriting\RewritingResolver;
use Thelia\Tools\URL;
use Thelia\Model\ConfigQuery;
/**
 * A trait for managing Rewritten URLs from model classes
 */
trait UrlRewritingTrait {

    /**
     * @returns string the view name of the rewritten object (e.g., 'category', 'product')
     */
    protected abstract function getRewrittenUrlViewName();

    /**
     * Get the object URL for the given locale, rewritten if rewriting is enabled.
     *
     * @param string $locale a valid locale (e.g. en_US)
     */
    public function getUrl($locale = null)
    {
        if(null === $locale) {
            $locale = $this->getLocale();
        }
        return URL::getInstance()->retrieve($this->getRewrittenUrlViewName(), $this->getId(), $locale)->toString();
    }

    /**
     * Generate a rewritten URL from the object title, and store it in the rewriting table
     *
     * @param string $locale a valid locale (e.g. en_US)
     */
    public function generateRewrittenUrl($locale)
    {
        if ($this->isNew()) {
            throw new \RuntimeException(sprintf('Object %s must be saved before generating url', $this->getRewrittenUrlViewName()));
        }
        // Borrowed from http://stackoverflow.com/questions/2668854/sanitizing-strings-to-make-them-url-and-filename-safe

        $this->setLocale($locale);

        $generateEvent = new GenerateRewrittenUrlEvent($this, $locale);

        $this->dispatchEvent(TheliaEvents::GENERATE_REWRITTENURL, $generateEvent);


        if($generateEvent->isRewritten())
        {
            return $generateEvent->getUrl();
        }

        $title = $this->getTitle();

        if(null == $title) {
            throw new \RuntimeException('Impossible to create an url if title is null');
        }
        // Replace all weird characters with dashes
        $string = preg_replace('/[^\w\-~_\.]+/u', '-', $title);

        // Only allow one dash separator at a time (and make string lowercase)
        $cleanString = mb_strtolower(preg_replace('/--+/u', '-', $string), 'UTF-8');

        $urlFilePart = rtrim($cleanString, '.-~_') . ".html";

        // TODO :
        // check if URL url already exists, and add a numeric suffix, or the like
        try{
            $i=0;
            while(URL::getInstance()->resolve($urlFilePart)) {
                $i++;
                $urlFilePart = sprintf("%s-%d.html",$cleanString, $i);
            }
        } catch (UrlRewritingException $e) {
            $rewritingUrl = new RewritingUrl();
            $rewritingUrl->setUrl($urlFilePart)
                ->setView($this->getRewrittenUrlViewName())
                ->setViewId($this->getId())
                ->setViewLocale($locale)
                ->save()
            ;
        }

        return $urlFilePart;

    }

    /**
     * return the rewritten URL for the given locale
     *
     * @param string $locale a valid locale (e.g. en_US)
     * @return null
     */
    public function getRewrittenUrl($locale)
    {
        $rewritingUrl = RewritingUrlQuery::create()
            ->filterByViewLocale($locale)
            ->filterByView($this->getRewrittenUrlViewName())
            ->filterByViewId($this->getId())
            ->filterByRedirected(null)
            ->findOne()
        ;

        if($rewritingUrl) {
            $url = $rewritingUrl->getUrl();
        } else {
            $url = null;
        }

        return $url;
    }

    /**
     * Mark the current URL as obseolete
     */
    public function markRewritenUrlObsolete() {
        RewritingUrlQuery::create()
            ->filterByView($this->getRewrittenUrlViewName())
            ->filterByViewId($this->getId())
            ->update(array(
                "View" => ConfigQuery::getObsoleteRewrittenUrlView()
            ));
    }

    /**
     * Set the rewritten URL for the given locale
     *
     * @param string $locale a valid locale (e.g. en_US)
     * @param $url
     * @return $this
     * @throws UrlRewritingException
     * @throws \Thelia\Exception\UrlRewritingException
     */
    public function setRewrittenUrl($locale, $url)
    {
        $currentUrl = $this->getRewrittenUrl($locale);
        if($currentUrl == $url || null === $url) {
            /* no url update */
            return $this;
        }

        try {
            $resolver = new RewritingResolver($url);

            /* we can reassign old url */
            if(null === $resolver->redirectedToUrl) {
                /* else ... */
                if($resolver->view == $this->getRewrittenUrlViewName() && $resolver->viewId == $this->getId()) {
                    /* it's an url related to the current object */

                    if($resolver->locale != $locale) {
                        /* it is an url related to this product for another locale */
                        throw new UrlRewritingException('URL_ALREADY_EXISTS', UrlRewritingException::URL_ALREADY_EXISTS);
                    }

                    if (count($resolver->otherParameters) > 0) {
                        /* it is an url related to this product but with more arguments */
                        throw new UrlRewritingException('URL_ALREADY_EXISTS', UrlRewritingException::URL_ALREADY_EXISTS);
                    }

                    /* here it must be a deprecated url */
                } else {
                    /* already related to another object */
                    throw new UrlRewritingException('URL_ALREADY_EXISTS', UrlRewritingException::URL_ALREADY_EXISTS);
                }
            }
        } catch(UrlRewritingException $e) {
            /* It's all good if URL is not found */
            if($e->getCode() !== UrlRewritingException::URL_NOT_FOUND) {
                throw $e;
            }
        }

        /* set the new URL */
        if(isset($resolver)) {
            /* erase the old one */
            $rewritingUrl = RewritingUrlQuery::create()->findOneByUrl($url);
            $rewritingUrl->setView($this->getRewrittenUrlViewName())
                ->setViewId($this->getId())
                ->setViewLocale($locale)
                ->setRedirected(null)
                ->save()
            ;

            /* erase additional arguments if any : only happens in case it erases a deprecated url */
            RewritingArgumentQuery::create()->filterByRewritingUrl($rewritingUrl)->deleteAll();
        } else {
            /* just create it */
            $rewritingUrl = new RewritingUrl();
            $rewritingUrl->setUrl($url)
                ->setView($this->getRewrittenUrlViewName())
                ->setViewId($this->getId())
                ->setViewLocale($locale)
                ->save()
            ;
        }

        /* deprecate the old one if needed */
        if (null !== $oldRewritingUrl = RewritingUrlQuery::create()->findOneByUrl($currentUrl)) {
            $oldRewritingUrl->setRedirected($rewritingUrl->getId())->save();
        }

        return $this;
    }
}