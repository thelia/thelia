import React from 'react';
import { CartItems } from '../../MiniCart/MiniCart';
import Title from '../../Title';
import Recap from '../Recap';

export default function Cart({ isVisible, cart, page }) {

  if (!isVisible) return null;

  const { title } = page;
  return (
    <div className="col-span-3 pr-0 Checkout-page Checkout-step1">
      <Title title={`${title}`} className="mb-8 Title--2" />
      <CartItems cart={cart} canDelete={true} />
      <Recap cart={cart} />
    </div>
  )
}
