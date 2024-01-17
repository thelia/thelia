import { createGlobalState } from 'react-use';

const initialState: {
  cart: boolean;
  login: boolean;
  redirectionToCheckout: boolean;
} = {
  cart: false,
  login: false,
  redirectionToCheckout: false
};

export const globalVisibility = createGlobalState(initialState);

export const useGlobalVisibility = () => {
  const [visibilityState, setVisibility] = globalVisibility();

  const hideCart = () => {
    setVisibility((visibility) => ({ ...visibility, cart: false }));
  };

  const showCart = () => {
    setVisibility((visibility) => ({ ...visibility, cart: true }));
  };

  const toggleCart = () => {
    setVisibility((visibility) => ({ ...visibility, cart: !visibility.cart }));
  };

  const showLogin = (redirectionToCheckout: boolean = false) => {
    setVisibility((visibility) => ({
      ...visibility,
      login: true,
      redirectionToCheckout
    }));
  };

  const hideLogin = (redirectionToCheckout: boolean = false) => {
    setVisibility((visibility) => ({
      ...visibility,
      login: false,
      redirectionToCheckout
    }));
  };

  const toggleLogin = () => {
    setVisibility((visibility) => ({
      ...visibility,
      login: !visibility.login
    }));
  };

  return {
    visibilityState: visibilityState,
    actions: {
      hideCart: hideCart,
      showCart: showCart,
      toggleCart: toggleCart,
      showLogin: showLogin,
      hideLogin: hideLogin,
      toggleLogin: toggleLogin
    }
  };
};
