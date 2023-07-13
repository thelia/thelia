import React, {
  Suspense,
  useLayoutEffect,
  useRef,
  useState,
  useEffect
} from 'react';
import { showLogin, hideCart } from '@js/redux/modules/visibility';
import {
  useCartItemDelete,
  useCartItemUpdate,
  useCartQuery,
  useCustomer
} from '@openstudio/thelia-api-utils';
import { useDispatch, useSelector } from 'react-redux';

import AddCoupon from '../AddCoupon';
import { ReactComponent as IconCLose } from '@icons/close.svg';
import { ReactComponent as IconTrash } from '@icons/trash.svg';
import useLockBodyScroll from '@utils/useLockBodyScroll';
import { useClickAway } from 'react-use';
import Loader from '../Loader';
import priceFormat from '@utils/priceFormat';
import { useIntl } from 'react-intl';
import Quantity from '../Quantity';
import useEscape from '@js/utils/useEscape';
import closeAndFocus from '@js/utils/closeAndFocus';
import { trapTabKey } from '@js/standalone/trapItemsMenu';
import Price from './Price/Price';
import Delete from './Delete/Delete';
import CartItem from './CartItem/CartItem';
import { CartItem as CartItemInterface, Cart } from '@js/types/common';
import { MiniCartProps } from './MiniCart.types';
import Total from './Total/Total';
import CartItems from './CartItems/CartItems';

function EmptyCart() {
  const intl = useIntl();
  return (
    <legend className="Title Title--3 text-center">
      <button
        type="button"
        className="SideBar-close"
        aria-label={intl.formatMessage({ id: 'CLOSE_CART' })}
        data-close-cart
      >
        <IconCLose className="pointer-events-none h-3 w-3" />
      </button>
      {intl.formatMessage({ id: 'CART_EMPTY' })}
    </legend>
  );
}

function MiniCart({ visible, redirect }: MiniCartProps) {
  const dispatch = useDispatch();
  const ref = useRef(null);
  const focusRef = useRef<HTMLButtonElement>(null);
  const { data } = useCartQuery();
  const cart = (data as Cart) || {};
  const { data: customer } = useCustomer(
    (document.querySelector('.MiniLogin-root') as any).dataset.login || false
  );
  const intl = useIntl();
  const [totalQuantityCart, setTotalQuantityCart] = useState(
    cart?.items?.length || 0
  );

  useLayoutEffect(() => {
    if (cart) {
      let count = 0;
      cart?.items?.forEach((el) => {
        count = count + (el?.quantity || 0);
      });
      setTotalQuantityCart(count);
    }
  }, [cart]);

  useEffect(() => {
    const node = document.getElementById('js-cart-count');
    if (node) {
      node.innerText = `${totalQuantityCart || 0}`;
    }
  }, [totalQuantityCart]);

  useLockBodyScroll(ref, visible, redirect);

  useClickAway(ref, (e) => {
    if (!(e.target as any)?.matches('[data-toggle-cart]') && visible) {
      closeAndFocus(() => dispatch(hideCart()), '[data-toggle-cart]');
    }
  });

  useLayoutEffect(() => {
    if (visible && focusRef.current) {
      focusRef.current.focus();
    }
  }, [focusRef, visible]);

  useEscape(ref, () =>
    closeAndFocus(() => dispatch(hideCart()), '[data-toggle-cart]')
  );

  (ref?.current as HTMLElement | null)?.addEventListener('keydown', (e) => {
    if (!visible) return;

    trapTabKey(ref.current, e);
  });

  return (
    <div ref={ref} className="grid grid-cols-1 gap-8">
      <button
        ref={focusRef}
        type="button"
        aria-label={intl.formatMessage({ id: 'CLOSE_CART' })}
        className="SideBar-close"
        data-close-cart
        onClick={() =>
          closeAndFocus(() => dispatch(hideCart()), '[data-toggle-cart]')
        }
        tabIndex={visible ? 0 : -1}
      >
        <IconCLose className="pointer-events-none h-3 w-3" />
      </button>
      {!cart.items || cart.items.length === 0 ? (
        <EmptyCart />
      ) : (
        <>
          <div className="flex items-center justify-between ">
            <strong className="Title Title--3">mon panier</strong>
            <span className="block text-base font-bold text-gray-600">
              {totalQuantityCart +
                ' article' +
                (totalQuantityCart > 1 ? 's' : '')}
            </span>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-4">
            <Total
              label={intl.formatMessage({ id: 'TOTAL' })}
              value={priceFormat(cart.total + cart.delivery)}
            />
            <a
              href="/order/delivery"
              className={`Button ${
                cart.items?.length <= 0 ? 'opacity-50' : ''
              }`}
              onClick={(e) => {
                if (cart.items?.length === 0) {
                  e.preventDefault();
                }
                if (!customer) {
                  e.preventDefault();
                  dispatch(
                    showLogin({ showCart: true, redirectionToCheckout: true })
                  );
                }
              }}
              tabIndex={visible ? 0 : -1}
            >
              {intl.formatMessage({ id: 'SUBMIT_CART' })}
            </a>
          </div>
          <CartItems cart={cart} visible={visible} />
        </>
      )}
    </div>
  );
}

export default function MiniCartWrapper() {
  const { cart: visible, redirectionToCheckout: redirect } = useSelector(
    (state: any) => state.visibility
  );

  return (
    <div className={`SideBar ${visible ? 'SideBar--visible' : ''} `}>
      <Suspense fallback={<Loader />}>
        {visible ? <MiniCart visible={visible} redirect={redirect} /> : null}
      </Suspense>
    </div>
  );
}
