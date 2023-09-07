import React, { Suspense } from 'react';

import Title from '../../Title';
import Loader from '../../Loader';
import PaymentModules from '../PaymentModules';
import PhoneCheck from '../PhoneCheck';
import { useSetCheckout } from '@openstudio/thelia-api-utils';
import { useIntl } from 'react-intl';
import { Checkout, CheckoutPageType } from '@js/types/checkout.types';
import { CheckoutRequest } from '@openstudio/thelia-api-utils/build/main/types';
import { useGlobalCheckout } from '@js/state/checkout';

export default function Payment({
  isVisible,
  checkout,
  page
}: {
  isVisible: boolean;
  checkout: CheckoutRequest;
  page?: CheckoutPageType;
}) {
  const { title } = page ? page : { title: '' };
  // const phoneCheck = useSelector(
  //   (state: any) => state.checkout.phoneNumberValid
  // );

  const { checkoutState } = useGlobalCheckout();
  const { phoneNumberValid: phoneCheck } = checkoutState;

  const { mutate } = useSetCheckout();
  const intl = useIntl();

  if (!isVisible) return null;

  return (
    <div className="Checkout-page col-span-2">
      <Title title={`${title}`} className="Title--2 mb-8" />
      <Suspense fallback={<Loader />}>
        <PaymentModules />
      </Suspense>

      {checkout?.paymentModuleId &&
      (checkout?.deliveryAddressId || checkout.pickupAddress) ? (
        <PhoneCheck addressId={checkout?.deliveryAddressId} />
      ) : null}
      {checkout?.paymentModuleId && phoneCheck && (
        <label className="Checkbox mt-8">
          <input
            type="checkbox"
            id="validTerms"
            checked={checkout.acceptedTermsAndConditions}
            onChange={() => {
              mutate({
                ...checkout,
                acceptedTermsAndConditions: !checkout.acceptedTermsAndConditions
              });
            }}
          />
          <span
            className={`text-base ${
              checkout?.acceptedTermsAndConditions ? 'text-main' : ''
            }`}
          >
            {intl.formatMessage({ id: 'ACCEPT' })}{' '}
            <a
              className="inline text-main"
              href={window.CGV_URL}
              target="_blank"
              rel="noreferrer noopener"
            >
              {intl.formatMessage({ id: 'CGV' })}
            </a>{' '}
            *
          </span>
        </label>
      )}
    </div>
  );
}
