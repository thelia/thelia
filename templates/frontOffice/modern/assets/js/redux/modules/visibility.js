import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  cart: false,
  login: false,
  redirectionToCheckout: false,
};

export const visibilitySlice = createSlice({
  name: 'visibility',
  initialState,
  reducers: {
    showCart: (state) => {
      state.cart = true;
    },
    hideCart: (state) => {
      state.cart = false;
    },
    toggleCart: (state) => {
      state.cart = !state.cart;
    },
    showLogin: (state, action) => {
      state.login = true;
      state.redirectionToCheckout = action.payload?.redirectionToCheckout || false;
    },
    hideLogin: (state, action) => {
      state.login = false;
      state.redirectionToCheckout = action.payload?.redirectionToCheckout || false;
    },
    toggleLogin: (state) => {
      state.login = !state.login;
    }
  }
});

export const {
  showCart,
  hideCart,
  toggleCart,
  showLogin,
  hideLogin,
  toggleLogin
} = visibilitySlice.actions;

export default visibilitySlice.reducer;
