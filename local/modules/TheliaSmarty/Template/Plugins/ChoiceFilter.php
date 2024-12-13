<?php

namespace TheliaSmarty\Template\Plugins;

use Propel\Runtime\Collection\ObjectCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ChoiceFilterOtherQuery;
use Thelia\Model\ChoiceFilterQuery;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use TheliaSmarty\TheliaSmarty;

class ChoiceFilter extends AbstractSmartyPlugin
{
    public function __construct(private readonly RequestStack $requestStack, private readonly SecurityContext $securityContext)
    {
    }

    public function templateChoiceFilter(array $params, \Smarty_Internal_Template $template): void
    {
        if (!$this->securityContext->hasAdminUser()) {
            return;
        }
        $templateId = $params['template_id'];

        $locales = $this->getEditLocales();

        $features = ChoiceFilterQuery::findFeaturesByTemplateId($templateId, $locales);
        $attributes = ChoiceFilterQuery::findAttributesByTemplateId($templateId, $locales);
        $others = ChoiceFilterOtherQuery::findOther($locales);

        /** @var \ChoiceFilter\Model\ChoiceFilter[] $choiceFilters */
        $choiceFilters = ChoiceFilterQuery::create()
            ->filterByTemplateId($templateId)
            ->orderByPosition()
            ->find();

        if (count($choiceFilters)) {
            $enabled = true;
        } else {
            $enabled = false;
        }

        $filters = self::merge($choiceFilters, $features, $attributes, $others);

        $template->assign('filters',$filters);
        $template->assign('enabled',$enabled);
    }

    public function categoryChoiceFilter(array $params, \Smarty_Internal_Template $template): void
    {
        if (!$this->securityContext->hasAdminUser()) {
            return;
        }

        $locales = $this->getEditLocales();

        $category = CategoryQuery::create()->filterById($params['category_id'])->findOne();

        $templateId = null;
        $categoryId = null;
        $choiceFilters = ChoiceFilterQuery::findChoiceFilterByCategory($category, $templateId, $categoryId);

        $messageInfo = [];
        $enabled = false;

        if ($templateId === null) {
            $features = new ObjectCollection();
            $attributes = new ObjectCollection();
            $others = new ObjectCollection();
            $choiceFilters = new ObjectCollection();

            $messageInfo[] = Translator::getInstance()->trans('This category uses no filter configuration',[],TheliaSmarty::DOMAIN_NAME);
        } else {
            $features = ChoiceFilterQuery::findFeaturesByTemplateId(
                $templateId,
                $locales
            );
            $attributes = ChoiceFilterQuery::findAttributesByTemplateId(
                $templateId,
                $locales
            );
            $others = ChoiceFilterOtherQuery::findOther();

            if (null === $categoryId) {
                $messageInfo[] = Translator::getInstance()->trans('This category uses the template configuration %templateId', ['%templateId' => $templateId],TheliaSmarty::DOMAIN_NAME);
            } elseif ($categoryId == $category->getId()) {
                $enabled = true;
                $messageInfo[] = Translator::getInstance()->trans('This category uses its own filter configuration',[],TheliaSmarty::DOMAIN_NAME);
            } else {
                $messageInfo[] = Translator::getInstance()->trans('This category uses the filter configuration of the category %categoryId', ['%categoryId' => $categoryId],TheliaSmarty::DOMAIN_NAME);
            }
        }

        $filters = self::merge($choiceFilters, $features, $attributes, $others);

        $template->assign('category_id', $params['category_id']);
        $template->assign('filters', $filters);
        $template->assign('enabled', $enabled);
        $template->assign('messageInfo', $messageInfo);
    }

    private function getEditLocales(): array
    {
        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession();

        $locale = $session->getAdminEditionLang()->getLocale();

        return [$locale];
    }

    public function getPluginDescriptors(): array
    {
        return [
            new SmartyPluginDescriptor('function', 'categoryChoiceFilter', $this, 'categoryChoiceFilter'),
            new SmartyPluginDescriptor('function', 'templateChoiceFilter', $this, 'templateChoiceFilter'),
        ];
    }

    private static function merge($choiceFilters, $features, $attributes, $others): array
    {
        $featuresArray = array_map(static function ($feature) {
            return array_merge($feature, ['Type' => 'feature', 'Visible' => 1]);
        }, $features->toArray());

        $attributesArray = array_map(static function ($attribute) {
            return array_merge($attribute, ['Type' => 'attribute', 'Visible' => 1]);
        }, $attributes->toArray());

        $othersArray = array_map(static function ($other) {
            return $other;
        }, $others->toArray());

        if (count($choiceFilters)) {
            $merge = [];
            foreach ($choiceFilters as $choiceFilter) {
                if (null !== $attributeId = $choiceFilter->getAttributeId()) {
                    foreach ($attributesArray as $key => $attributeArray) {
                        if ($attributeId == $attributeArray['Id']) {
                            $attributeArray['Visible'] = $choiceFilter->getVisible() ? 1 : 0;
                            $merge[] = $attributeArray;
                            unset($attributesArray[$key]);
                        }
                    }
                } elseif (null !== $featureId = $choiceFilter->getFeatureId()) {
                    foreach ($featuresArray as $key => $featureArray) {
                        if ($featureId == $featureArray['Id']) {
                            $featureArray['Visible'] = $choiceFilter->getVisible() ? 1 : 0;
                            $merge[] = $featureArray;
                            unset($featuresArray[$key]);
                        }
                    }
                } elseif (null !== $type = $choiceFilter->getChoiceFilterOther()->getType()) { // todo ajouter une jointure pour le type
                    foreach ($othersArray as $key => $otherArray) {
                        if ($type == $otherArray['Type']) {
                            $otherArray['Visible'] = $choiceFilter->getVisible() ? 1 : 0;
                            $merge[] = $otherArray;
                            unset($othersArray[$key]);
                        }
                    }
                }
            }

            $merge = array_merge($merge, $attributesArray);
            $merge = array_merge($merge, $featuresArray);
            $merge = array_merge($merge, $othersArray);
        } else {
            $merge = array_merge($attributesArray, $featuresArray);
            $merge = array_merge($merge, $othersArray);
        }

        $p = 1;
        $merge = array_map(static function ($array) use (&$p) {
            return array_merge($array, ['Position' => $p++]);
        }, $merge);

        return $merge;
    }
}
