import 'react-phone-number-input/style.css';

import {
  useAddressQuery,
  useAddressUpdate
} from '@openstudio/thelia-api-utils';

import Alert from '../Alert';
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import React from 'react';

export default function PhoneCheck({ addressId }) {
  const [phone, setPhone] = React.useState('');
  const { data = [] } = useAddressQuery();
  const { mutate: update, isSuccess, isError } = useAddressUpdate();

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
    setPhone(address?.cellphoneNumber || '');
  }, [address]);

  const isValid = isValidPhoneNumber(phone || '');

  if (!address) return null;

  return (
    <form
      className="shadow panel"
      onSubmit={async (e) => {
        e.preventDefault();
        try {
          await update({
            id: addressId,
            data: {
              ...address,
              cellphoneNumber: phone
            }
          });
        } catch (error) {
          console.error(error);
        }
      }}
    >
      <div className="flex flex-col gap-6 pb-6 text-xl font-bold border-b border-gray-300 xl:items-center xl:flex-row">
        <div className="flex-1 text-xl font-bold">Votre numéro de contact</div>
      </div>
      <div className="flex mt-4">
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
      {isSuccess ? (
        <Alert type="success" message="Numéro de contact mis à jour" />
      ) : null}
    </form>
  );
}
