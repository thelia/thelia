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
