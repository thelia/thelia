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
 * @author Manuel Raynaud <manu@raynaud.io>
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
    protected $fontName;

    /**
     * @param string $content     html content to transform into pdf
     * @param string $orientation page orientation, same as TCPDF
     * @param string $format      The format used for pages, same as TCPDF
     * @param string $lang        Lang : fr, en, it...
     * @param bool   $unicode     TRUE means that the input text is unicode (default = true)
     * @param string $encoding    charset encoding; default is UTF-8
     * @param array  $marges      Default marges (left, top, right, bottom)
     * @param string $fontName    Default font name
     */
    public function __construct(
        $content,
        $orientation = 'P',
        $format = 'A4',
        $lang = 'fr',
        $unicode = true,
        $encoding = 'UTF-8',
        array $marges = [ 0, 0, 0, 0],
        $fontName = 'freesans'
    ) {
        $this->content = $content;
        $this->orientation = $orientation;
        $this->format = $format;
        $this->lang = $lang;
        $this->unicode = $unicode;
        $this->encoding = $encoding;
        $this->marges = $marges;
        $this->fontName = $fontName;
    }

    /**
     * @param mixed $content
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @param array $marges
     * @return $this
     */
    public function setMarges($marges)
    {
        $this->marges = $marges;

        return $this;
    }

    /**
     * @return array
     */
    public function getMarges()
    {
        return $this->marges;
    }

    /**
     * @param mixed $orientation
     * @return $this
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
     * @return $this
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

    /**
     * @return mixed
     */
    public function getFontName()
    {
        return $this->fontName;
    }

    /**
     * @param string $fontName
     * @return $this
     */
    public function setFontName($fontName)
    {
        $this->fontName = $fontName;
        return $this;
    }
}
