import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  cart: false,
  login: false
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
    },
    hideLogin: (state) => {
      state.login = false;
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
