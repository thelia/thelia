import React from 'react';
import { CartItems } from '../../MiniCart/MiniCart';
import Title from '../../Title';
import Recap from '../Recap';

import { Cart } from '@js/types/common';
import { CheckoutPageType } from '@js/types/checkout.types';

export default function Cart({
  isVisible,
  cart,
  page
}: {
  isVisible: boolean;
  cart: Cart;
  page?: CheckoutPageType;
}) {
  if (!isVisible) return null;

  const { title } = page ? page : { title: '' };
  return (
    <div className="Checkout-page Checkout-step1 col-span-3 pr-0">
      <Title title={`${title}`} className="Title--2 mb-8" />
      <CartItems cart={cart} canDelete={true} visible={isVisible} noOverflow />
      <Recap cart={cart} />
    </div>
  );
}
