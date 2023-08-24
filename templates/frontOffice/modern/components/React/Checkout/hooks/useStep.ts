import { useState, useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { setCheckoutStep } from '@js/redux/modules/checkout';
import { Checkout } from '@js/types/checkout.types';
export default function useStep(checkout: Checkout) {
  const [step, setStep] = useState(1);
  const dispatch = useDispatch();
  const {
    mode: selectedMode,
    phoneCheck = true,
    checkoutStep
  } = useSelector((state: any) => state.checkout);

  useEffect(() => {
    if (checkoutStep) {
      setStep(checkoutStep);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId &&
      phoneCheck &&
      checkout?.paymentModuleId &&
      checkout?.acceptedTermsAndConditions &&
      checkout?.isComplete
    ) {
      setStep(7);
      dispatch(setCheckoutStep(7));
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId &&
      phoneCheck &&
      checkout?.paymentModuleId
    ) {
      setStep(6);
      dispatch(setCheckoutStep(6));
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId &&
      phoneCheck
    ) {
      setStep(5);
      dispatch(setCheckoutStep(5));
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId
    ) {
      setStep(4);
      dispatch(setCheckoutStep(4));
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId
    ) {
      setStep(3);
      dispatch(setCheckoutStep(3));
    } else if (selectedMode !== null) {
      dispatch(setCheckoutStep(3));
    } else {
      setStep(1);
    }
  }, [checkout, setStep, selectedMode, phoneCheck]);

  return step;
}
