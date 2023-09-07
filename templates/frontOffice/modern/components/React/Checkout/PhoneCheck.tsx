import 'react-phone-number-input/style.css';

import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import {
  useAddressQuery,
  useAddressUpdate
} from '@openstudio/thelia-api-utils';

import Alert from '../Alert';
import React from 'react';
import { parsePhoneNumber } from 'react-phone-number-input';
import Title from '../Title';

import { useIntl } from 'react-intl';
import { useGlobalCheckout } from '@js/state/checkout';

export default function PhoneCheck({ addressId }: { addressId: number }) {
  const [phone, setPhone] = React.useState('');
  const [isSubmitted, setIsSubmitted] = React.useState(false);
  const [isDirty, setIsDirty] = React.useState(false);
  const { data = [] } = useAddressQuery();

  const { actions } = useGlobalCheckout();

  const intl = useIntl();
  const {
    mutate: update,
    isSuccess,
    isError,
    reset,
    isLoading
  } = useAddressUpdate();
  const address = React.useMemo(() => {
    if (addressId && Array.isArray(data)) {
      return data.find((a) => a.id === addressId);
    }

    if (!addressId && Array.isArray(data)) {
      return data.find((a) => a.isDefault);
    }

    return null;
  }, [data, addressId]);

  React.useEffect(() => {
    if (!address) return;
    let currentPhoneNumber = address?.cellphone || '';

    if (address?.cellphone && address?.countryCode) {
      currentPhoneNumber = parsePhoneNumber(
        address?.cellphone,
        address?.countryCode
      )?.number;
    }
    setPhone(currentPhoneNumber);
  }, [address]);

  React.useEffect(() => {
    if (addressId !== undefined) {
      reset();
    }
  }, [addressId, reset]);

  React.useEffect(() => {
    if (isValidPhoneNumber(phone || '') && !isError) {
      actions.setPhoneNumberValid(true);
    }
  }, [isError, phone, isSuccess]);

  const isValid = phone ? isValidPhoneNumber(phone || '') : false;

  if (!address) return null;

  return (
    <div>
      <Title title="CONTACT_NUMBER" className="Title--3 text-2xl" />
      <small className="text-gray-600 ">
        {intl.formatMessage({ id: 'NOTICE_PHONE_CHECK' })}
      </small>
      <form
        className={`PhoneCheck ${isLoading ? 'PhoneCheck--loading' : ''} ${
          isSubmitted && !isValid ? 'PhoneCheck--error' : ''
        }`}
        onChange={() => setIsSubmitted(false)}
        onFocus={() => setIsDirty(true)}
        onSubmit={async (e) => {
          e.preventDefault();
          if (isValid) {
            try {
              await update({
                id: addressId,
                data: {
                  ...address,
                  cellphone: phone
                }
              });
            } catch (error) {
              console.error(error);
            }
          }
        }}
      >
        <div className="PhoneCheck-field relative">
          <PhoneInput
            international={false}
            defaultCountry={address?.countryCode}
            value={phone}
            onChange={() => setPhone}
          />
          <button
            type="submit"
            className="PhoneCheck-btn absolute right-0"
            onClick={() => setIsSubmitted(true)}
          >
            {intl.formatMessage({ id: 'CONFIRM' })}
          </button>
        </div>
        {isDirty && isValid && !isSuccess ? (
          <Alert
            message={intl.formatMessage({ id: 'VALID_NUMBER_PHONE' })}
            className="mt-4"
          />
        ) : null}
        {isSubmitted && !isValid ? (
          <Alert
            type="error"
            message={intl.formatMessage({ id: 'INVALID_NUMBER_PHONE' })}
            className="mt-4"
          />
        ) : null}
        {isSubmitted && isError ? (
          <Alert
            type="error"
            message={intl.formatMessage({ id: 'ERROR_DURING_UPDATE' })}
            className="mt-4"
          />
        ) : null}
        {isSubmitted && !isError && isValid && isSuccess ? (
          <Alert
            type="success"
            message={intl.formatMessage({ id: 'NUMBER_UPDATED' })}
            className="mt-4"
          />
        ) : null}
      </form>
    </div>
  );
}
