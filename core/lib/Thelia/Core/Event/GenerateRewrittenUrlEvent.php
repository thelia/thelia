<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event;

/**
 * Class GenerateRewrittenUrlEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class GenerateRewrittenUrlEvent extends ActionEvent
{
    /** @var string local */
    protected $url;

    /**
     * GenerateRewrittenUrlEvent constructor.
     * @param string $locale
     */
    public function __construct(
        protected $object,
        /** @var string local */
        protected $locale
    )
    {
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return $this
     */
    public function setObject($object)
    {
        $this->object = $object;

        return $this;
    }

    /**
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRewritten()
    {
        return null !== $this->url;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
}
