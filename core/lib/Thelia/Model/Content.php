<?php

namespace Thelia\Model;

use Thelia\Model\Base\Content as BaseContent;
use Thelia\Tools\URL;

class Content extends BaseContent
{
    public function getUrl($locale)
    {
        return URL::init()->retrieve('content', $this->getId(), $locale);
    }
}
