<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Component\Form\FormFactoryBuilderInterface;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Form\TheliaFormValidator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ValidatorBuilder::class)
        ->call('setTranslationDomain', ['%thelia.validator.translation_domain%'])
        ->call('setTranslator', [service('thelia.translator')]);

    $services->alias('thelia.forms.validator_builder', ValidatorBuilder::class);

    $services->set(FormFactoryBuilderInterface::class, FormFactoryBuilder::class);

    $services->alias('thelia.form_factory_builder', FormFactoryBuilderInterface::class);

    $services->set(HttpFoundationExtension::class);

    $services->alias('thelia.forms.extension.http_foundation_extension', HttpFoundationExtension::class);

    $services->set(CoreExtension::class);

    $services->alias('thelia.forms.extension.core_extension', CoreExtension::class);

    $services->alias('thelia.form_factory', TheliaFormFactory::class)
        ->public();

    $services->alias('thelia.form_validator', TheliaFormValidator::class)
        ->public();
};
