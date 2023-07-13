import { Cart, CartItem, DeliveryModule } from '@js/types/common';

export interface MiniCartProps {
  visible: boolean;
  redirect: boolean;
}

export interface MiniCartFooterProps {
  delivery: Cart['delivery'];
  deliveryTax: Cart['deliveryTax'];
  taxes: Cart['taxes'];
  discount: Cart['discount'];
  coupon?: string;
  total: Cart['total'];
  deliveryModule?: DeliveryModule | null;
  recap?: boolean;
}

export interface FooterItemProps {
  label: string;
  value: number;
}

export interface TotalProps {
  label: string;
  value: number;
}

export interface CartItemsProps {
  cart: Cart;
  visible: boolean;
  canDelete?: boolean;
  canChangeQuantity?: boolean;
  isCartPage?: boolean;
  recap?: boolean;
  noOverflow?: boolean;
}

export interface ItemProps extends CartItem {
  visible: boolean;
  canDelete: boolean;
  canChangeQuantity: boolean;
  recap: boolean;
}

export interface DeleteProps {
  id: number;
  setRemoveItem: React.Dispatch<React.SetStateAction<boolean>>;
  visible: boolean;
}

export interface PriceProps {
  price: number;
  isPromo: boolean;
  price_promo: number;
}
