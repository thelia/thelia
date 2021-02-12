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

namespace Thelia\Core\Event\Content;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Content;

/**
 * Class ContentEvent.
 *
 * @author manuel raynaud <manu@raynaud.io>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\ContentEvent
 */
class ContentEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Content
     */
    protected $content;

    public function __construct(Content $content = null)
    {
        $this->content = $content;
    }

    public function setContent(Content $content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return \Thelia\Model\Content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * check if content exists.
     *
     * @return bool
     */
    public function hasContent()
    {
        return null !== $this->content;
    }
}
