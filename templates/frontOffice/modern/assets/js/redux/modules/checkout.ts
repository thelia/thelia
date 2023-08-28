import { createSlice } from '@reduxjs/toolkit';

const initialState: {
  mode: null;
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

export const checkoutSlice = createSlice({
  name: 'checkout',
  initialState,
  reducers: {
    setMode: (state, action) => {
      state.mode = action.payload;

      state.deliveryAddressId = initialState.deliveryAddressId;
      state.deliveryModuleId = initialState.deliveryModuleId;
      state.deliveryModuleOption = initialState.deliveryModuleOption;
    },
    setPhoneNumberValid: (state, action) => {
      state.phoneNumberValid = action.payload;
    },
    setCheckoutStep: (state, action) => {
      state.checkoutStep = action.payload;
    }
  }
});

export const { setMode, setPhoneNumberValid, setCheckoutStep } =
  checkoutSlice.actions;

export default checkoutSlice.reducer;
