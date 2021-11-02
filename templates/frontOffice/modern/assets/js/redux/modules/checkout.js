import { createSlice } from '@reduxjs/toolkit';

let initialState = {
  mode: null
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
    }
  }
});

export const { setMode } = checkoutSlice.actions;

export default checkoutSlice.reducer;
