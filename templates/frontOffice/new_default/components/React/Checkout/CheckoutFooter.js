import { useIntl } from 'react-intl';
import React, { useState, useEffect } from 'react';
import useEnableCta from './hooks/useEnableCta';
import { useDispatch } from 'react-redux';
import { setCheckoutStep } from '@js/redux/modules/checkout';
import { CHECKOUT_STEP } from './constants';
import { useFinalCheckout } from '@openstudio/thelia-api-utils';
import Loader from '../Loader';

export function CheckoutFooter({ step, checkout }) {
  const intl = useIntl();
  const enabledCta = useEnableCta(step, checkout);
  const { mutate: final, isLoading } = useFinalCheckout();
  const dispatch = useDispatch();
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
      dispatch(setCheckoutStep(step + 1));
      window.scrollTo(0, { behavior: 'smooth' });
    }
  };

  return (
    <>
      <footer className="Checkout-footer">
        <div className="flex items-center justify-center h-full Checkout-container sm:justify-end">
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
