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

namespace Thelia\Core\Event\Template;

class TemplateDuplicateEvent extends TemplateEvent
{
    /** @var  int */
    protected $sourceTemplateId;
    
    /** @var  string */
    protected $locale;
    
    /**
     * TemplateCreateEvent constructor.
     * @param int $sourceTemplateId
     */
    public function __construct($sourceTemplateId, $locale)
    {
        parent::__construct();
        
        $this->sourceTemplateId = $sourceTemplateId;
        $this->locale = $locale;
    }
    
    /**
     * @return int
     */
    public function getSourceTemplateId()
    {
        return $this->sourceTemplateId;
    }
    
    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }
}
