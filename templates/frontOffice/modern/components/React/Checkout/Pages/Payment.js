import React, { Suspense } from 'react';
import { useSelector } from 'react-redux';
import Title from '../../Title';
import Loader from '../../Loader';
import PaymentModules from '../PaymentModules';
import PhoneCheck from '../PhoneCheck';
import { useSetCheckout } from '@openstudio/thelia-api-utils';
import { useIntl } from 'react-intl';

export default function Payment({ isVisible, checkout, page }) {
  const { title } = page;
  const phoneCheck = useSelector((state) => state.checkout.phoneNumberValid);
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
        (checkout?.deliveryAddressId || checkout.pickupAddress) && (
          <PhoneCheck addressId={checkout?.deliveryAddressId} />
        )}
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
