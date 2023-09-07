import { useIntl } from 'react-intl';
import React, { useState, useEffect } from 'react';
import useEnableCta from './hooks/useEnableCta';

import { CHECKOUT_STEP } from './constants';
import { useFinalCheckout } from '@openstudio/thelia-api-utils';
import Loader from '../Loader';
import { CheckoutRequest } from './type';
import { Checkout } from '@js/types/checkout.types';
import { useGlobalCheckout } from '@js/state/checkout';

export function CheckoutFooter({
  step,
  checkout
}: {
  step: number;
  checkout: CheckoutRequest;
}) {
  const intl = useIntl();
  const enabledCta = useEnableCta(step, checkout);
  const { mutate: final, isLoading } = useFinalCheckout();

  const { actions } = useGlobalCheckout();
  const [currentStep, setCurrentStep] = useState(
    Object.values(CHECKOUT_STEP).find((s) => s.id === step)
  );

  useEffect(() => {
    setCurrentStep(Object.values(CHECKOUT_STEP).find((s) => s.id === step));
  }, [setCurrentStep, step]);

  const handleClick = async () => {
    if (step === 4) {
      try {
        final(checkout);
      } catch (error) {
        console.error(error);
      }
    } else {
      actions.setCheckoutStep(step + 1);
      window.scrollTo({ behavior: 'smooth' });
    }
  };

  return (
    <>
      <footer className="Checkout-footer">
        <div className="Checkout-container flex h-full items-center justify-center sm:justify-end">
          <button
            type="button"
            onClick={handleClick}
            className={`Button px-4 text-base md:text-lg  ${
              enabledCta ? 'Button--actived' : ''
            }`}
            disabled={!enabledCta}
          >
            {intl.formatMessage({
              id: currentStep?.ctaLabel
                ? `${currentStep?.ctaLabel}`
                : 'SUBMIT_CART'
            })}
          </button>
        </div>
      </footer>
      {isLoading && (
        <div className="Checkout-finalOverlay">
          <Loader />
        </div>
      )}
    </>
  );
}
