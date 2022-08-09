import 'react-phone-number-input/style.css';

import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import {
  useAddressQuery,
  useAddressUpdate
} from '@openstudio/thelia-api-utils';
import { setPhoneNumberValid } from '@redux/modules/checkout';
import Alert from '../Alert';
import React from 'react';
import { parsePhoneNumber } from 'react-phone-number-input';
import Title from '../Title';
import { useDispatch } from 'react-redux';
import { useIntl } from 'react-intl';

export default function PhoneCheck({ addressId }) {
  const [phone, setPhone] = React.useState('');
  const { data = [] } = useAddressQuery();
  const dispatch = useDispatch();
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
      ).number;
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
      dispatch(setPhoneNumberValid(true));
    }
  }, [isError, phone, isSuccess, dispatch]);

  const isValid = phone ? isValidPhoneNumber(phone || '') : false;

  if (!address) return null;

  return (
    <div className={`Checkout-block`}>
      <Title title="Votre numéro de contact" step={5} />
      <form
        className={`PhoneCheck ${
          isLoading ? 'pointer-events-none opacity-50' : ''
        } ${!isValid ? 'PhoneCheck--error' : ''}`}
        onSubmit={async (e) => {
          e.preventDefault();
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
        }}
      >
        <div className="PhoneCheck-field">
          <PhoneInput
            international={false}
            defaultCountry={address?.countryCode}
            value={phone}
            onChange={setPhone}
          />
          <button type="submit" className="PhoneCheck-btn" disabled={!isValid}>
            {intl.formatMessage({ id: 'UPDATE' })}
          </button>
        </div>
        {isValid && !isSuccess ? (
          <Alert
            type="success"
            message="Ce numéro de contact est valide"
            className="mt-4"
          />
        ) : null}
        {!isValid ? (
          <Alert
            type="error"
            message="Votre numéro de contact est invalide"
            className="mt-4"
          />
        ) : null}
        {isError ? (
          <Alert
            type="error"
            message="erreur lors de la mise à jour"
            className="mt-4"
          />
        ) : null}
        {!isError && isValid && isSuccess ? (
          <Alert
            type="success"
            message="Numéro de contact mis à jour"
            className="mt-4"
          />
        ) : null}
      </form>
    </div>
  );
}
