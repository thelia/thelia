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

namespace HookAnalytics\Form;

use HookAnalytics\HookAnalytics;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\LangQuery;

/**
 * Class Configuration.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Configuration extends BaseForm
{
    protected function buildForm(): void
    {
        $form = $this->formBuilder;

        $lang = $this->getRequest()->getSession()->get('thelia.admin.edition.lang');
        if (!$lang) {
            $lang = LangQuery::create()->filterByByDefault(1)->findOne();
        }

        $value = HookAnalytics::getConfigValue('hookanalytics_trackingcode', '', $lang->getLocale());
        $form->add(
            'trackingcode',
            TextType::class,
            [
                'data' => $value,
                'label' => Translator::getInstance()->trans('Tracking Code'),
                'label_attr' => [
                    'for' => 'trackingcode',
                ],
            ]
        );
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'hookanalytics';
    }
}
