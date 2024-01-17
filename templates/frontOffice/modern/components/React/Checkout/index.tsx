import React, { Suspense, useEffect } from 'react';

import { CHECKOUT_STEP } from './constants';
import {
  useCartQuery,
  useGetCheckout,
  useSetCheckout
} from '@openstudio/thelia-api-utils';

import Loader from '../Loader';
import Steps from '../Steps';
import { CheckoutFooter } from './CheckoutFooter';
import { CartItems } from '../MiniCart/MiniCart';
import Delivery from './Pages/Delivery';
import Invoice from './Pages/Invoice';
import Payment from './Pages/Payment';
import Cart from './Pages/Cart';
import Recap from './Recap';
import Title from '../Title';
import { useGlobalCheckout } from '@js/state/checkout';

export default function Checkout() {
  const { data: checkout } = useGetCheckout();
  const { data: cart = {} } = useCartQuery();
  const { checkoutState } = useGlobalCheckout();
  const { checkoutStep } = checkoutState;

  const { mutate: reset } = useSetCheckout();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [checkoutStep]);

  useEffect(() => {
    //@ts-ignore
    reset({ deliveryAddressId: null });
  }, [reset]);

  useEffect(() => {
    if (cart?.items?.length < 1) {
      window.location.href = '/';
    }
  }, [cart]);

  return (
    <>
      <Steps steps={CHECKOUT_STEP} />
      <Suspense fallback={<Loader />}>
        <section className="Checkout-container Checkout-main">
          <Cart
            isVisible={checkoutStep === CHECKOUT_STEP.CART?.id}
            page={CHECKOUT_STEP.CART}
            cart={cart}
          />
          <Delivery
            isVisible={checkoutStep === CHECKOUT_STEP.DELIVERY?.id}
            page={CHECKOUT_STEP.DELIVERY}
            checkout={checkout}
          />
          <Invoice
            isVisible={checkoutStep === CHECKOUT_STEP.INVOICE?.id}
            page={CHECKOUT_STEP.INVOICE}
            checkout={checkout}
          />
          <Payment
            isVisible={checkoutStep === CHECKOUT_STEP.PAYMENT?.id}
            page={CHECKOUT_STEP.PAYMENT}
            checkout={checkout}
          />
          {checkoutStep !== 1 && (
            <div className="-mx-5 mt-8 bg-gray-200 px-5 py-8 md:m-0 md:bg-transparent md:p-0">
              <Title title="RECAP_ORDER" className="Title--3 mb-7 text-2xl" />
              {/* <CartItems
                cart={cart}
                canDelete={false}
                evenClass={false}
                canChangeQuantity={false}
                recap={true}
              /> */}
              <Recap cart={cart} small={true} />
            </div>
          )}
        </section>
      </Suspense>

      <CheckoutFooter checkout={checkout} step={checkoutStep} />
    </>
  );
}
