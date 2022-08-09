import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  mode: null,
  phoneNumberValid: false
};

export const checkoutSlice = createSlice({
  name: 'checkout',
  initialState,
  reducers: {
    setMode: (state, action) => {
      state.mode = action.payload;

      state.deliveryAddress = initialState.deliveryAddress;
      state.deliveryModule = initialState.deliveryModule;
      state.deliveryModuleOption = initialState.deliveryModuleOption;
    },
    setPhoneNumberValid: (state, action) => {
      state.phoneNumberValid = action.payload;
    }
  }
});

export const { setMode, setPhoneNumberValid } = checkoutSlice.actions;

export default checkoutSlice.reducer;
