import {
  useDeliveryModulessQuery,
  useGetCheckout,
  usePaymentModulessQuery
} from '@openstudio/thelia-api-utils';
import { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';

export function useValidDeliveryModules(type) {
  const { data: checkout, isLoading } = useGetCheckout();
  const { data = [], isLoading: isDeliveryModuleLoading } =
    useDeliveryModulessQuery(checkout?.deliveryAddressId);

  const validDeliveryModules = data.filter(
    (m) => m.valid && m.options?.length > 0
  );

  return {
    data: type
      ? validDeliveryModules.filter((m) => m.deliveryMode === type)
      : validDeliveryModules,
    isLoading: isLoading || isDeliveryModuleLoading
  };
}

export function useValidPaymentModules(type) {
  const { data = [], isLoading } = usePaymentModulessQuery();

  const validModules = data.filter((m) => m.valid);

  return {
    data: type
      ? validModules.filter((m) => m.deliveryMode === type)
      : validModules,
    isLoading: isLoading
  };
}

export function useStep(checkout) {
  const [step, setStep] = useState(1);
  const { mode: selectedMode, phoneCheck = true } = useSelector(
    (state) => state.checkout
  );
  useEffect(() => {
    if (
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
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId &&
      phoneCheck &&
      checkout?.paymentModuleId
    ) {
      setStep(6);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId &&
      phoneCheck
    ) {
      setStep(5);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId &&
      checkout?.billingAddressId
    ) {
      setStep(4);
    } else if (
      selectedMode !== null &&
      checkout?.deliveryModuleOptionCode &&
      checkout?.deliveryAddressId
    ) {
      setStep(3);
    } else if (selectedMode !== null) {
      setStep(2);
    } else {
      setStep(1);
    }
  }, [checkout, setStep, selectedMode, phoneCheck]);

  return step;
}
