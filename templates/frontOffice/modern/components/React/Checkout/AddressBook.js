import React, { useMemo } from 'react';
import { setBillingAddress, setDeliveryAddress } from '@redux/modules/checkout';
import {
  useAddressDelete,
  useAddressQuery
} from '@openstudio/thelia-api-utils';
import { useDispatch, useSelector } from 'react-redux';

import Checkbox from '../Checkbox';
import CreateAddressModal from '../Address/CreateAddressModal';
import EditAddressModal from '../Address/EditAddressModal';
import { useIntl } from 'react-intl';

function Address({ address = {} }) {
  const { isSuccess: deleteSuccess } = useAddressDelete();

  if (deleteSuccess) return null;

  return (
    <div className="">
      <address className="text-sm">
        <div className="not-italic font-bold">{address.label}</div>
        <div>
          {address.civilityTitle?.short} {address.firstName} {address.lastName}
        </div>
        <span className="block street-address">{address.address1}</span>
        {address.address2 ? (
          <span className="block street-address">
            {address.address2} - {address.address3 ? address.address3 : null}
          </span>
        ) : null}
        <span className="block postal-code">
          {address.zipcode} {address.city} {address.countryCode}
        </span>
      </address>

      <div className="mt-4">
        <EditAddressModal address={address} />
      </div>
    </div>
  );
}

function useSelectedAddress(type) {
  let address = null;

  const { billingAddress, deliveryAddress } = useSelector(
    (state) => state.checkout
  );

  if (type === 'delivery' && deliveryAddress?.id) {
    address = deliveryAddress;
  } else if (type === 'billing' && billingAddress?.id) {
    address = billingAddress;
  }
  return address;
}
function useChooseAddressDispatch(type) {
  return useMemo(() => {
    if (type === 'delivery') {
      return setDeliveryAddress;
    } else if (type === 'billing') {
      return setBillingAddress;
    }
  }, [type]);
}

function IsBillingAddressSameAsDeliveryAddress({ isSame }) {
  const intl = useIntl();
  const dispatch = useDispatch();
  const deliveryAddress = useSelector(
    ({ checkout }) => checkout.deliveryAddress
  );

  return (
    <div>
      <Checkbox
        label={intl.formatMessage({ id: 'SAME_ADDRESS' })}
        name="sameAddress"
        checked={isSame}
        onChange={() => {
          if (!isSame) {
            dispatch(setBillingAddress({ ...deliveryAddress }));
          } else {
            dispatch(setBillingAddress(null));
          }
        }}
      />
    </div>
  );
}

function AddressBook({ mode, title }) {
  const dispatch = useDispatch();
  const { data = [] } = useAddressQuery();
  const selectedAddress = useSelectedAddress(mode);
  const dispatchSetAddress = useChooseAddressDispatch(mode);
  const { isSame = false, checkoutMode } = useSelector(({ checkout }) => ({
    checkoutMode: checkout.mode,
    isSame:
      checkout.billingAddress?.id &&
      checkout.deliveryAddress?.id &&
      checkout.billingAddress.id === checkout.deliveryAddress.id
  }));

  return (
    <div className="pb-0 shadow panel">
      <div className="flex flex-col gap-6 pb-6 text-xl font-bold border-b border-gray-300 xl:items-center xl:flex-row">
        <div className="flex-1 text-xl font-bold">{title}</div>
        {mode !== 'billing' ? <CreateAddressModal /> : null}
      </div>

      {checkoutMode === 'pickup' ||
      (mode === 'billing' && checkoutMode === 'delivery') ? (
        <div className="py-4 border-gray-300">
          <IsBillingAddressSameAsDeliveryAddress isSame={isSame} />
        </div>
      ) : null}

      {mode === 'delivery' || (mode === 'billing' && !isSame) ? (
        <div className="grid gap-4 py-4 lg:grid-cols-2">
          {data.map((address) => {
            return (
              <button
                key={address.id}
                className={`p-4 shadow text-left hover:bg-main-dark hover:text-white ${
                  address.id === selectedAddress?.id ? 'bg-main text-white' : ''
                }`}
                onClick={() => {
                  dispatch(dispatchSetAddress({ ...address, touched: true }));
                }}
              >
                <Address
                  address={address}
                  isSelected={address.id === selectedAddress?.id}
                />
              </button>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}

export default AddressBook;
