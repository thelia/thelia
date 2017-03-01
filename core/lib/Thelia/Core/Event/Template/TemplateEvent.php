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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Template;

class TemplateEvent extends ActionEvent
{
    /**
     * @var Template
     */
    protected $template = null;

    public function __construct(Template $template = null)
    {
        $this->template = $template;
    }
    
    /**
     * @return bool
     */
    public function hasTemplate()
    {
        return ! is_null($this->template);
    }
    
    /**
     * @return Template
     */
    public function getTemplate()
    {
        return $this->template;
    }
    
    /**
     * @param Template $template
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }
}
