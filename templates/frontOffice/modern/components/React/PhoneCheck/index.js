import 'react-phone-number-input/style.css';

import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import {
  useAddressQuery,
  useAddressUpdate
} from '@openstudio/thelia-api-utils';

import Alert from '../Alert';
import React from 'react';
import { parsePhoneNumber } from 'react-phone-number-input';

export default function PhoneCheck({ addressId }) {
  const [phone, setPhone] = React.useState('');
  const { data = [] } = useAddressQuery();
  const { mutate: update, isSuccess, isError, reset } = useAddressUpdate();

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
      currentPhoneNumber =
        parsePhoneNumber(address.cellphone, address.countryCode)?.number || '';
    }
    setPhone(currentPhoneNumber);
  }, [address]);

  React.useEffect(() => {
    if (addressId !== undefined) {
      reset();
    }
  }, [addressId, reset]);

  const isValid = phone ? isValidPhoneNumber(phone || '') : false;

  if (!address) return null;

  return (
    <form
      className="panel shadow"
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
      <div className="flex flex-col gap-6 border-b border-gray-300 pb-6 text-xl font-bold xl:flex-row xl:items-center">
        <div className="flex-1 text-xl font-bold">Votre numéro de contact</div>
      </div>
      <div className="mt-4 flex">
        <PhoneInput
          international={false}
          defaultCountry={address?.countryCode}
          value={phone}
          onChange={setPhone}
        />

        <button type="submit" className="btn btn--sm" disabled={!isValid}>
          Modifier
        </button>
      </div>
      {!isValid ? (
        <Alert type="error" message="Votre numéro de contact est invalide" />
      ) : null}
      {isError ? (
        <Alert type="error" message="erreur lors de la mise à jour" />
      ) : null}
      {!isError && isValid && isSuccess ? (
        <Alert type="success" message="Numéro de contact mis à jour" />
      ) : null}
    </form>
  );
}
