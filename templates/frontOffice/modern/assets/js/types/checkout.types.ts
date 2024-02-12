import {
  Address,
  CheckoutAddress,
  CustomerState
} from '@components/React/Checkout/type';
import { DeliveryModule, PickupAddress } from './common';

export type CheckoutPageType = {
  id: number;
  slug: string;
  label: string;
  title: string;
  ctaLabel: string;
};

export type Mode = 'delivery' | 'pickup' | null;

export declare type CheckoutRequest = {
  needValidate: boolean;
  deliveryModuleId: number | null;
  paymentModuleId: number;
  billingAddressId: number;
  deliveryAddressId: number;
  deliveryModuleOptionCode: string | null;
  pickupAddress: unknown;
  acceptedTermsAndConditions: boolean;
};

export declare type CheckoutResponse = {
  deliveryModuleId: number | null;
  paymentModuleId: number;
  billingAddressId: number;
  deliveryAddressId: number;
  deliveryModuleOptionCode: string | null;
  pickupAddress: {
    [index: string]: unknown;
  };
  acceptedTermsAndConditions: boolean;
  isComplete: boolean;
};

export type Checkout = {
  step: number;
  customer: CustomerState;
  deliveryAddressId: CheckoutAddress['id'] | Address['id'] | null;
  billingAddressId: CheckoutAddress['id'] | Address['id'] | null;
  needValidate: CheckoutRequest['needValidate'];
  deliveryModule: DeliveryModule | null;
  paymentModuleId: number | null;
  deliveryModuleOptionCode: 'string' | null;
  pickupAddress: PickupAddress | null;
  acceptedTermsAndConditions: boolean;
  isComplete: boolean;
};
