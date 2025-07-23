<?php

declare(strict_types=1);

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
 * Class PdfEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class PdfEvent extends ActionEvent
{
    protected $pdf;

    /**
     * @param string $content      html content to transform into pdf
     * @param string $orientation  page orientation, same as TCPDF
     * @param string $format       The format used for pages, same as TCPDF
     * @param string $lang         Lang : fr, en, it...
     * @param bool   $unicode      TRUE means that the input text is unicode (default = true)
     * @param string $encoding     charset encoding; default is UTF-8
     * @param array  $marges       Default marges (left, top, right, bottom)
     * @param string $fontName     Default font name
     * @param string $templateName
     * @param string $fileName
     * @param string $object
     */
    public function __construct(protected $content, protected $orientation = 'P', protected $format = 'A4', protected $lang = 'fr', protected $unicode = true, protected $encoding = 'UTF-8', protected array $marges = [0, 0, 0, 0], protected $fontName = 'freesans', protected $templateName = null, protected $fileName = null, protected $object = null)
    {
    }

    /**
     * @return $this
     */
    public function setContent($content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setPdf($pdf): static
    {
        $this->pdf = $pdf;

        return $this;
    }

    public function getPdf()
    {
        return $this->pdf;
    }

    public function hasPdf(): bool
    {
        return null !== $this->pdf;
    }

    /**
     * @return $this
     */
    public function setEncoding($encoding): static
    {
        $this->encoding = $encoding;

        return $this;
    }

    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @return $this
     */
    public function setFormat($format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @return $this
     */
    public function setLang($lang): static
    {
        $this->lang = $lang;

        return $this;
    }

    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @return $this
     */
    public function setMarges(array $marges): static
    {
        $this->marges = $marges;

        return $this;
    }

    public function getMarges(): array
    {
        return $this->marges;
    }

    /**
     * @return $this
     */
    public function setOrientation($orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function getOrientation()
    {
        return $this->orientation;
    }

    /**
     * @return $this
     */
    public function setUnicode($unicode): static
    {
        $this->unicode = $unicode;

        return $this;
    }

    public function getUnicode()
    {
        return $this->unicode;
    }

    public function getFontName()
    {
        return $this->fontName;
    }

    /**
     * @return $this
     */
    public function setFontName(string $fontName): static
    {
        $this->fontName = $fontName;

        return $this;
    }

    public function getTemplateName()
    {
        return $this->templateName;
    }

    /**
     * @return $this
     */
    public function setTemplateName(string $templateName): static
    {
        $this->templateName = $templateName;

        return $this;
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @return $this
     */
    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return $this
     */
    public function setObject(string $object): static
    {
        $this->object = $object;

        return $this;
    }
}
