import React from 'react';
import Title from '../../Title';
import Recap from '../Recap';
import CartItems from '../../MiniCart/CartItems/CartItems';

export default function Cart({ isVisible, cart, page }) {
  if (!isVisible) return null;

  const { title } = page;
  return (
    <div className="Checkout-page Checkout-step1 col-span-3 pr-0">
      <Title title={`${title}`} className="Title--2 mb-8" />
      <CartItems cart={cart} canDelete={true} noOverflow />
      <Recap cart={cart} />
    </div>
  );
}
