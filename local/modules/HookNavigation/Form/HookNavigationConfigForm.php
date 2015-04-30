<?php

namespace HookNavigation\Form;

use HookNavigation\HookNavigation;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;
use Thelia\Model\Base\FolderI18nQuery;

/**
 * Class HookNavigationConfigForm
 * @package HookNavigation\Form
 * @author Etienne PERRIERE <eperriere@openstudio.fr> - OpenStudio
 */
class HookNavigationConfigForm extends BaseForm {

    public function getName()
    {
        return "hooknavigation_configuration";
    }

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'footer_body_folder_id',
                'choice',
                [
                    'choices' => $this->getFolders(),
                    'constraints' => [
                        new NotBlank()
                    ],
                    "label" => $this->translator->trans('Folder in footer body', [], HookNavigation::MESSAGE_DOMAIN.'.bo.default')
                ]
            )
            ->add(
                'footer_bottom_folder_id',
                'choice',
                [
                    'choices' => $this->getFolders(),
                    'constraints' => [
                        new NotBlank(),
                    ],
                    "label" => $this->translator->trans('Folder in footer bottom', [], HookNavigation::MESSAGE_DOMAIN.'.bo.default')
                ]
            );
    }

    public function getFolders()
    {
        $folders = [];
        $foldersQuery = FolderI18nQuery::create()
            ->filterByLocale($this->getRequest()->getPreferredLanguage())
            ->find();

        if  (count($foldersQuery->getData()) != 0) {
            $i = 0;
            foreach ($foldersQuery as $folder) {
                $folders[$folder->getId()] = $folder->getTitle();
                $i++;
            }
        }

        return $folders;
    }
}