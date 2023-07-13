export interface PSE {
  id: number;
  isDefault: boolean;
  isPromo: boolean;
  isNew: boolean;
  ref: string;
  ean: string;
  quantity: number;
  weight: number;
  price: number;
  untaxedPrice: number;
  promoPrice: number;
  promoUntaxedPrice: number;
  combination: [];
  typeDistribution: string | null;
  displayPrice: string;
  isRental: boolean | false;
}

export interface Attribute {
  id: string;
  title: string;
  values: { id: string; label: string }[];
}
