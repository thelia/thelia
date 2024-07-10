import { DeliveryModule, PickupAddress } from '@js/types/common';

export type Address = {
  id: number;
  isDefault: number;
  label: string;
  customer: {
    id: number;
    birthday: string;
    civilityTitle: {
      id: number;
      short: string;
      long: string;
    };
    reference: string;
    firstName: string;
    lastName: string;
    email: string;
    rememberMe: boolean;
    discount: number;
    reseller: boolean;
    defaultAddressId: number;
  };
  civilityTitle: {
    id: number;
    short: string;
    long: string;
  };
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
  countryId: number;
  countryCode: string;
  stateCode: string;
  stateName: string;
  additionalData: {};
};

export type AddressMode = 'billing' | 'delivery' | null;

export type CheckoutAddress = Partial<Pick<Address, 'id'>> &
  (Pick<
    Address,
    | 'isDefault'
    | 'label'
    | 'firstName'
    | 'lastName'
    | 'company'
    | 'address1'
    | 'address2'
    | 'address3'
    | 'zipCode'
    | 'city'
    | 'countryCode'
    | 'countryId'
    | 'phone'
    | 'cellphone'
  > & {
    customer: {
      birthday: string;
    };
    civilityTitle: {
      id: number;
    };
  });

export interface CustomerState {
  status:
    | 'CHECKING'
    | 'UNKNOWN'
    | 'NEED_LOGIN'
    | 'NEED_REGISTER'
    | 'LOGGED_IN'
    | 'NEW_CUSTOMER';
  email: string | null;
  newsletterSubscribed: boolean;
  customerType: null | string;
  customerTypeValidated?: boolean;
}

export type Checkout = {
  step: number;
  customer: CustomerState;
  deliveryAddressId: CheckoutAddress['id'] | Address['id'] | null;
  billingAddressId: CheckoutAddress['id'] | Address['id'] | null;
  needValidate: CheckoutRequest['needValidate'];
  deliveryModule: DeliveryModule | null;
  paymentModuleId: number | null;
  pickupAddress: PickupAddress | null;
  acceptedTermsAndConditions: boolean;
};

export declare type CheckoutRequest = {
  needValidate: boolean;
  deliveryModuleId: number;
  paymentModuleId: number;
  billingAddressId: number;
  deliveryAddressId: number;
  deliveryModuleOptionCode: string;
  pickupAddress: unknown;
  acceptedTermsAndConditions: boolean;
};

export type NestedKeyOf<ObjectType extends object> = {
  [Key in keyof ObjectType & (string | number)]: ObjectType[Key] extends object
    ? `${Key}` | `${Key}.${NestedKeyOf<ObjectType[Key]>}`
    : `${Key}`;
}[keyof ObjectType & (string | number)];

export type NestedValueOf<Obj, Key extends string> = Obj extends object
  ? Key extends `${infer Parent}.${infer Leaf}`
    ? Parent extends keyof Obj
      ? NestedValueOf<Obj[Parent], Leaf>
      : never
    : Key extends keyof Obj
    ? Obj[Key]
    : never
  : never;

export type CheckoutKeys = NestedKeyOf<Checkout>;
