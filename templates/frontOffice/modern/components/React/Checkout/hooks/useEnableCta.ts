import { useGlobalCheckout } from '@js/state/checkout';
import { CheckoutRequest } from '@js/types/checkout.types';
import { useEffect, useState } from 'react';

export default function useEnableCta(
  currentStep = 1,
  checkout: CheckoutRequest
) {
  const { checkoutState } = useGlobalCheckout();
  const { phoneNumberValid, mode: selectedMode } = checkoutState;

  const [enabledCta, setEnabledCta] = useState(false);

  useEffect(() => {
    setEnabledCta(false);
    switch (currentStep) {
      case 4:
        if (checkout.acceptedTermsAndConditions && phoneNumberValid) {
          setEnabledCta(true);
        }
        break;
      case 3:
        if (
          selectedMode !== null &&
          checkout?.deliveryModuleId &&
          checkout?.deliveryModuleOptionCode &&
          (checkout?.deliveryAddressId || checkout.pickupAddress) &&
          checkout?.billingAddressId
        ) {
          setEnabledCta(true);
        }
        break;
      case 2:
        setEnabledCta(false);
        if (
          selectedMode !== null &&
          checkout?.deliveryModuleId &&
          checkout?.deliveryModuleOptionCode &&
          (checkout?.deliveryAddressId || checkout.pickupAddress)
        ) {
          setEnabledCta(true);
        }
        break;
      case 1:
        setEnabledCta(true);
        break;
      default:
        setEnabledCta(false);
    }
  }, [checkout, selectedMode, currentStep, phoneNumberValid]);

  return enabledCta;
}
