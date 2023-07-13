import CartItem from '../CartItem/CartItem';
import { CartItem as CartItemInterface } from '@js/types/common';
import { CartItemsProps } from '../MiniCart.types';

export default function CartItems({
  cart,
  canDelete = true,
  canChangeQuantity = true,
  recap = false,
  noOverflow = false,
  visible
}: CartItemsProps) {
  return (
    <div className="CartItems-wrapper">
      <div
        className={`CartItems ${recap ? 'CartItems--recap' : ''} ${
          noOverflow ? 'max-h-max overflow-visible' : ''
        }`}
      >
        {cart.items?.map((item: CartItemInterface, index: number) => (
          <CartItem
            key={item.id || index}
            canDelete={canDelete}
            canChangeQuantity={canChangeQuantity}
            recap={recap}
            visible={visible}
            {...item}
          />
        ))}
      </div>
    </div>
  );
}
