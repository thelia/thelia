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
    let visibility = { ...visibilityState };

    visibility.cart = false;
    setVisibility(visibility);
  };
  const showCart = () => {
    let visibility = { ...visibilityState };

    visibility.cart = true;
    setVisibility(visibility);
  };
  const toggleCart = () => {
    let visibility = { ...visibilityState };

    visibility.cart = !visibility.cart;
    setVisibility(visibility);
  };

  const showLogin = (redirectionToCheckout: boolean) => {
    let visibility = { ...visibilityState };

    visibility.login = true;
    visibility.redirectionToCheckout = redirectionToCheckout || false;
    console.log('show');
    setVisibility(visibility);
  };

  const hideLogin = (redirectionToCheckout: boolean) => {
    let visibility = { ...visibilityState };

    visibility.login = false;
    visibility.redirectionToCheckout = redirectionToCheckout || false;
    console.log('hide');
    setVisibility(visibility);
  };

  const toggleLogin = () => {
    let visibility = { ...visibilityState };

    visibility.login = !visibility.login;
    console.log('toggle', visibility);
    setVisibility(visibility);
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
