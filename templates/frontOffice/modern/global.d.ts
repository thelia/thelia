declare module '*.svg' {
  import * as React from 'react';

  export const ReactComponent: React.FunctionComponent<
    React.SVGProps<SVGSVGElement> & { title?: string }
  >;
}

interface Window {
  apiUtils: any;
  SVG_SPRITE_URL?: string;
  COUNTRIES?: import('@components/React/Address/AddressForm').COUNTRY[];
  CUSTOMER_TITLES: import('@js/types/common').CivilityTitle[];
  PSES: import('@components/React/PseSelector/PseSelector.types').PSE[];
  ATTRIBUTES: import('@components/React/PseSelector/PseSelector.types').Attribute[];
  SCRIPTRECAPTCHA: string;
  CGV_URL: string;
  DEFAULT_CURRENCY_CODE: string;
}
