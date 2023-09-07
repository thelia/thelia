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
    let checkout = { ...checkoutState };

    checkout.mode = mode;

    checkout.deliveryAddressId = initialState.deliveryAddressId;
    checkout.deliveryModuleId = initialState.deliveryModuleId;
    checkout.deliveryModuleOption = initialState.deliveryModuleOption;

    setCheckout(checkout);
  };

  const setPhoneNumberValid = (value: boolean) => {
    let checkout = { ...checkoutState };

    checkout.phoneNumberValid = value;
    setCheckout(checkout);
  };

  const setCheckoutStep = (step: number) => {
    let checkout = { ...checkoutState };

    checkout.checkoutStep = step;
    setCheckout(checkout);
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
