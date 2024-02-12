import { createGlobalState } from 'react-use';

const initialState: {
  mode: string | null;
  phoneNumberValid: boolean;
  checkoutStep: number;
  deliveryAddressId?: number;
  deliveryModuleId?: number;
  deliveryModuleOption?: string;
} = {
  mode: null,
  phoneNumberValid: false,
  checkoutStep: 1
};

export const globalCheckoutState = createGlobalState(initialState);

export const useGlobalCheckout = () => {
  const [checkoutState, setCheckout] = globalCheckoutState();

  const setMode = (mode: string) => {
    setCheckout((checkout) => ({
      ...checkout,
      mode: mode,
      deliveryAddressId: initialState.deliveryAddressId,
      deliveryModuleId: initialState.deliveryModuleId,
      deliveryModuleOption: initialState.deliveryModuleOption
    }));
  };

  const setPhoneNumberValid = (value: boolean) => {
    setCheckout((checkout) => ({
      ...checkout,
      phoneNumberValid: value
    }));
  };

  const setCheckoutStep = (step: number) => {
    setCheckout((checkout) => ({
      ...checkout,
      checkoutStep: step
    }));
  };

  return {
    checkoutState: checkoutState,
    actions: {
      setMode: setMode,
      setPhoneNumberValid: setPhoneNumberValid,
      setCheckoutStep: setCheckoutStep
    }
  };
};
