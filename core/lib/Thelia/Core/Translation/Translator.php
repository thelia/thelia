<?php
namespace Thelia\Core\Translation;

use Symfony\Component\Translation\Translator as BaseTranslator;

class Translator extends BaseTranslator
{
    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function trans($id, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        if ($this->catalogues[$locale]->has((string) $id, $domain))
            return parent::trans($id, $parameters, $domain = 'messages', $locale = null);
        else
            return strtr($id, $parameters);
    }
}
