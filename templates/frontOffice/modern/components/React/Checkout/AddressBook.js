import React from 'react';

import {
  useAddressDelete,
  useAddressQuery,
  useGetCheckout,
  useSetCheckout
} from '@openstudio/thelia-api-utils';

import CreateAddressModal from '../Address/CreateAddressModal';
import EditAddressModal from '../Address/EditAddressModal';

function Address({ address = {}, onSelect = () => {}, isSelected }) {
  const { isSuccess: deleteSuccess } = useAddressDelete();

  if (deleteSuccess) return null;

  return (
    <div className="flex items-center justify-between w-full">
      <address className="mr-auto text-sm">
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

      <button onClick={onSelect}>
        {isSelected ? (
          <span className="mr-2 text-lg font-bold text-main">✓</span>
        ) : null}
        Choisir
      </button>
      <div className="ml-8">
        <EditAddressModal address={address} />
      </div>
    </div>
  );
}

function AddressBook({ mode, title }) {
  const { data = [] } = useAddressQuery();
  const { data: checkout } = useGetCheckout();
  const { mutate } = useSetCheckout();

  return (
    <div className="pb-0 shadow panel">
      <div className="flex flex-col gap-6 pb-6 text-xl font-bold border-b border-gray-300 xl:items-center xl:flex-row">
        <div className="flex-1 text-xl font-bold">{title}</div>
        {mode !== 'billing' ? <CreateAddressModal /> : null}
      </div>

      <div className="grid gap-4 py-4">
        {data.map((address) => {
          let isSelected = false;

          if (mode === 'delivery') {
            isSelected = address.id === checkout?.deliveryAddressId;
          }
          if (mode === 'billing') {
            isSelected = address.id === checkout?.billingAddressId;
          }

          return (
            <Address
              key={address.id}
              address={address}
              isSelected={isSelected}
              onSelect={() => {
                let request = {};

                if (mode === 'delivery') {
                  request.deliveryAddressId = address.id;
                  request.deliveryModuleId = null;
                  request.deliveryModuleOptionCode = null;
                  if (!checkout.billingAddressId) {
                    request.billingAddressId = address.id;
                  }
                }

                if (mode === 'billing') {
                  request.billingAddressId = address.id;
                }

                mutate({
                  ...checkout,
                  ...request
                });
              }}
            />
          );
        })}
      </div>
    </div>
  );
}

export default AddressBook;
