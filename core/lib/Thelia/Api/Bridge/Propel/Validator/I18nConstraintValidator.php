<?php

namespace Thelia\Api\Bridge\Propel\Validator;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Api\Resource\I18nCollection;

class I18nConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly TranslatorInterface $translator,private readonly RequestStack $requestStack)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof I18nCollection){
            throw new \RuntimeException('I18nConstraint attribute should be used on'.I18nCollection::class);
        }
        $request = $this->requestStack->getCurrentRequest();
        $method = $request?->getMethod();
        if (!$method || $method === HttpOperation::METHOD_PATCH){
            return;
        }
        $titleAndLocaleCount = 0;

        /** @var I18nCollection $i18nData */
        $i18nData = $value;
        foreach ($i18nData->i18ns as $i18n) {
            if ($i18n->getTitle() !== null && !empty($i18n->getTitle())) {
                ++$titleAndLocaleCount;
            }
        }

        if ($titleAndLocaleCount === 0) {
            $this->context->buildViolation(
                $this->translator->trans(
                    'The title and locale must be defined at least once.',
                    [], null, 'en_US'
                )
            )->addViolation();
        }
    }
}
