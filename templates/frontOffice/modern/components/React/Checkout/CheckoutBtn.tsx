import { useGlobalCheckout } from '@js/state/checkout';
import {
  useFinalCheckout,
  useGetCheckout,
  useSetCheckout
} from '@openstudio/thelia-api-utils';

import { useRef } from 'react';

import React from 'react';

import { useIntl } from 'react-intl';

export default function CheckoutButton() {
  const intl = useIntl();
  const { mutate } = useFinalCheckout();
  const { mutate: setCheckout } = useSetCheckout();
  const { data: checkout } = useGetCheckout();

  const { checkoutState } = useGlobalCheckout();
  const { phoneNumberValid } = checkoutState;

  const ButtonRef = useRef<HTMLInputElement>(null);
  return (
    <div className="">
      <label className="my-4 inline-block cursor-pointer">
        <input
          type="checkbox"
          className="h-5 w-5 border border-main text-main focus:border-main focus:ring-main"
          id="validTerms"
          onChange={() => {
            setCheckout({
              ...checkout,
              acceptedTermsAndConditions: !checkout.acceptedTermsAndConditions
            });
            ButtonRef?.current?.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
          }}
        />
        <span className="leading-0 ml-2 text-lg">
          {intl.formatMessage({ id: 'ACCEPT_CGV' })}
        </span>
      </label>

      <button
        className="Button mx-auto mt-8 block"
        onClick={async () => {
          mutate(checkout);
        }}
        disabled={
          !checkout?.isComplete ||
          !checkout.acceptedTermsAndConditions ||
          !phoneNumberValid
        }
      >
        {intl.formatMessage({ id: 'VALIDATE_CHECKOUT' })}
      </button>
    </div>
  );
}
