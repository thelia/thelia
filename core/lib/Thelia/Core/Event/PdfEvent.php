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

namespace Thelia\Core\Event;

/**
 * Class PdfEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class PdfEvent extends ActionEvent
{
    protected $content;

    protected $pdf;

    protected $orientation;
    protected $format;
    protected $lang;
    protected $unicode;
    protected $encoding;
    protected $marges;

    /**
     * @param $content              html content to transform into pdf
     * @param string $orientation page orientation, same as TCPDF
     * @param string $format      The format used for pages, same as TCPDF
     * @param string $lang        Lang : fr, en, it...
     * @param bool   $unicode     TRUE means that the input text is unicode (default = true)
     * @param string $encoding    charset encoding; default is UTF-8
     * @param array  $marges      Default marges (left, top, right, bottom)
     */
    public function __construct($content, $orientation = 'P', $format = 'A4', $lang='fr', $unicode=true, $encoding='UTF-8',array $marges = array(0, 0, 0, 0))
    {
        $this->content = $content;
        $this->orientation = $orientation;
        $this->format = $format;
        $this->lang = $lang;
        $this->unicode = $unicode;
        $this->encoding = $encoding;
        $this->marges = $marges;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content)
    {
        $this->content = $content;
		
	return $this;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    public function setPdf($pdf)
    {
        $this->pdf = $pdf;
		
	return $this;
    }

    public function getPdf()
    {
        return $this->pdf;
    }

    public function hasPdf()
    {
        return null !== $this->pdf;
    }

    /**
     * @param mixed $encoding
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
		
	return $this;
    }

    /**
     * @return mixed
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param mixed $format
     */
    public function setFormat($format)
    {
        $this->format = $format;
		
	return $this;
    }

    /**
     * @return mixed
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @param mixed $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
		
	return $this;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param mixed $marges
     */
    public function setMarges($marges)
    {
        $this->marges = $marges;
		
	return $this;
    }

    /**
     * @return mixed
     */
    public function getMarges()
    {
        return $this->marges;
    }

    /**
     * @param mixed $orientation
     */
    public function setOrientation($orientation)
    {
        $this->orientation = $orientation;
		
	return $this;
    }

    /**
     * @return mixed
     */
    public function getOrientation()
    {
        return $this->orientation;
    }

    /**
     * @param mixed $unicode
     */
    public function setUnicode($unicode)
    {
        $this->unicode = $unicode;
		
	return $this;
    }

    /**
     * @return mixed
     */
    public function getUnicode()
    {
        return $this->unicode;
    }

}
