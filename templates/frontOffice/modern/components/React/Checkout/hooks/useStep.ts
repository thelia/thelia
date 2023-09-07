import { useState, useEffect } from 'react';

import { Checkout } from '@js/types/checkout.types';
import { useGlobalCheckout } from '@js/state/checkout';
export default function useStep(checkout: Checkout) {
  const [step, setStep] = useState(1);

  const { checkoutState, actions } = useGlobalCheckout();

  const {
    mode: selectedMode,
    phoneNumberValid = true,
    checkoutStep
  } = checkoutState;

  useEffect(() => {
    if (checkoutStep) {
      setStep(checkoutStep);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId &&
      phoneNumberValid &&
      checkout?.paymentModuleId &&
      checkout?.acceptedTermsAndConditions &&
      checkout?.isComplete
    ) {
      setStep(7);
      actions.setCheckoutStep(7);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId &&
      phoneNumberValid &&
      checkout?.paymentModuleId
    ) {
      setStep(6);
      actions.setCheckoutStep(6);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId &&
      phoneNumberValid
    ) {
      setStep(5);
      actions.setCheckoutStep(5);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId
    ) {
      setStep(4);
      actions.setCheckoutStep(4);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId
    ) {
      setStep(3);
      actions.setCheckoutStep(3);
    } else if (selectedMode !== null) {
      actions.setCheckoutStep(3);
    } else {
      setStep(1);
    }
  }, [checkout, setStep, selectedMode, phoneNumberValid]);

  return step;
}
