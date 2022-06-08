import {
  useFinalCheckout,
  useGetCheckout,
  useSetCheckout
} from '@openstudio/thelia-api-utils';

import { useRef } from 'react';

import React from 'react';

import { useIntl } from 'react-intl';

export default function CheckoutBtn() {
  const intl = useIntl();
  const { mutate } = useFinalCheckout();
  const { mutate: setCheckout } = useSetCheckout();
  const { data: checkout } = useGetCheckout();

  const btnRef = useRef(null);

  return (
    <div className="">
      <label className="mb-4 block items-start">
        <input
          type="checkbox"
          className={`mt-1 border border-gray-300 text-main focus:border-gray-300 focus:ring-main`}
          id="validTerms"
          onChange={() => {
            setCheckout({
              ...checkout,
              acceptedTermsAndConditions: !checkout.acceptedTermsAndConditions
            });
            btnRef?.current?.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
          }}
        />
        <span className="leading-0 ml-2">
          {intl.formatMessage({ id: 'ACCEPT_CGV' })}
        </span>
      </label>

      <button
        className="btn w-full shadow"
        onClick={async () => {
          mutate(checkout);
        }}
        disabled={!checkout?.isComplete || !checkout.acceptedTermsAndConditions}
      >
        {intl.formatMessage({ id: 'VALIDATE_CHECKOUT' })}
      </button>
    </div>
  );
}
