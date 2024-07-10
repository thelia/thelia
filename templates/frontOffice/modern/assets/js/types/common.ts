export interface Cart {
  id: number;
  taxes: number;
  delivery: number;
  deliveryTax: number;
  coupon: string;
  discount: number;
  total: number;
  currency: string;
  virtual: boolean;
  items: CartItem[];
}

export interface Coupon {
  id: number;
  code: string;
  amount: number;
  title: string;
}

export interface CartItem {
  id: number;
  promo: boolean;
  product: Product;
  productSaleElement: ProductSaleElement;
  images: Image[];
  price: Price;
  promoPrice: Price;
  quantity: number;
}

export interface Image {
  i18n: I18N;
  id: number;
  url?: string;
  position?: number;
  visible: boolean;
}
export interface LibraryImage {
  id: number;
  imageId: number;
  image: {
    id: number;
    title: string | null;
    fileName: string | null;
    url: string;
    tags: string[];
    currentLocale: string;
  };
  itemType: string;
  itemId: number;
  code: string;
  visible: boolean;
  position: number;
  currentLocale: string;
}

export interface I18N {
  title: string;
  description: string;
  chapo: string;
  postscriptum: string;
  metaTitle?: string;
  metaDescription?: string;
  metaKeywords?: string;
}

export interface Price {
  untaxed: number;
  taxed: number;
}

export interface Prices {
  taxedPrice: number;
  untaxedPrice: number;
  displayPrice: 'taxed' | 'untaxed';
}

export interface Product {
  i18n: I18N;
  id: number;
  reference: string;
  url: string;
  virtual: boolean;
  visible: boolean;
  brand: Brand;
  defaultCategory: Image;
  categories: Image[];
  contents?: Image[];
  images: Image[];
  documents: Image[];
  features: Feature[];
  libraryImages?: LibraryImage[];
  productSaleElements: ProductSaleElement[];
}

export interface Brand {
  i18n: I18N;
  id: number;
  visible: boolean;
}

export interface Feature {
  i18n: I18N;
  id: number;
  values: Value[];
}

export interface Value {
  i18n: I18N;
  id: number;
}

export interface ProductSaleElement {
  id: number;
  promo: boolean;
  reference: string;
  attributes: Feature[];
  quantity: number;
  newness: boolean;
  weight: number;
  default: boolean;
  ean: string;
  images: Image[];
  documents: Image[];
  price: Price;
  promoPrice: Price;
  productId: number;
}

/**
 * Payment modules
 */

export interface PaymentModule {
  id: number;
  valid: boolean;
  code: string;
  minimumAmount: null;
  maximumAmount: null;
  images: Image[];
  currentLocale: string;
  i18n: I18N;
  optionGroups: PaymentOptionGroup[];
}

export interface PaymentOptionGroup {
  code: string,
  minimumSelectedOption: number;
  maximumSelectedOption: number;
  title: string,
  description: string,
  options: PaymentOption[],
}

export interface PaymentOption {
  code: string,
  title: string,
  description: string,
  currentLocale: string,
}

export interface Address {
  id: number | 'new';
  isDefault: number;
  label: string;
  customer: Customer;
  civilityTitle: CivilityTitle;
  firstName: string;
  lastName: string;
  company: string;
  address1: string;
  address2: string;
  address3: string;
  zipCode: string;
  city: string;
  countryCode: string;
  additionalData: null;
  titleId: number;
  customerId: number;
  countryId: number;
  phone: string;
  cellphone: string;
  stateCode: null;
  stateId: null;
  stateName: null;
  currentLocale: string;
}

export interface CivilityTitle {
  id: number;
  short: string;
  long: string;
  currentLocale: string;
  isDefault?: boolean;
}

export interface Customer {
  id: number;
  civilityTitle: CivilityTitle;
  lang: Lang;
  reference: string;
  firstName: string;
  lastName: string;
  email: string;
  rememberMe: null;
  discount: string;
  reseller: null;
  titleId: number;
  langId: number;
  defaultAddressId: number;
  currentLocale: string;
  birthday: string;
  customerType: string;
  customerTypeValidated: boolean;
}

export interface Lang {
  id: number;
  title: string;
  code: string;
  locale: string;
  url: string;
  dateFormat: string;
  timeFormat: string;
  datetimeFormat: string;
  decimalSeparator: string;
  thousandsSeparator: string;
  active: boolean;
  visible: number;
  decimals: string;
  byDefault: number;
  currentLocale: string;
}

// DeliveryModule

export type DeliveryModules = DeliveryModule[];
export interface DeliveryModule {
  deliveryMode: 'delivery' | 'pickup';
  id: number;
  valid: boolean;
  code: string;
  options: Option[];
  images: Image[];
  currentLocale: string;
  i18n: I18N;
}

export interface Option {
  code: string;
  valid: boolean;
  title: string;
  image: string;
  minimumDeliveryDate: string;
  maximumDeliveryDate: string;
  postage: number;
  postageTax: number;
  postageUntaxed: number;
  currentLocale: string;
}

export interface PickupLocation {
  id: string;
  latitude: string;
  longitude: string;
  title: string;
  address: PickupAddress;
  moduleId: number;
  moduleOptionCode: null;
  openingHours: string[];
}

export interface PickupAddress {
  id: string;
  isDefault: boolean;
  label: string;
  customer: Customer;
  civilityTitle: CivilityTitle;
  firstName: string;
  lastName: string;
  cellphone: string;
  phone: string;
  company: string;
  address1: string;
  address2: string;
  address3: string;
  zipCode: string;
  city: string;
  countryCode: string;
  stateCode: string;
  stateName: string;
  additionalData: { [key: string]: any };
}
