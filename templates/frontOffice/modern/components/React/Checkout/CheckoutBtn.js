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
      <label className="items-start block mb-4">
        <input
          type="checkbox"
          className={`border-gray-300 border text-main focus:border-gray-300 focus:ring-main mt-1`}
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
        <span className="ml-2 leading-0">
          {intl.formatMessage({ id: 'ACCEPT_CGV' })}
        </span>
      </label>

      <button
        className="w-full shadow btn"
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
