import React from 'react';
import Loader from '../Loader';

import {
  useAddressDelete,
  useAddressQuery,
  useGetCheckout,
  useSetCheckout
} from '@openstudio/thelia-api-utils';

import CreateAddressModal from '../Address/CreateAddressModal';
import EditAddressModal from '../Address/EditAddressModal';
import { useIntl } from 'react-intl';

function Address({ address = {}, onSelect = () => {}, isSelected }) {
  const { isSuccess: deleteSuccess } = useAddressDelete();
  const intl = useIntl();

  if (deleteSuccess) return null;

  return (
    <div className="flex w-full items-center justify-between">
      <address className="mr-auto text-sm">
        <div className="font-bold not-italic">{address.label}</div>
        <div>
          {address.civilityTitle?.short} {address.firstName} {address.lastName}
        </div>
        <span className="street-address block">{address.address1}</span>
        {address.address2 ? (
          <span className="street-address block">
            {address.address2} - {address.address3 ? address.address3 : null}
          </span>
        ) : null}
        <span className="postal-code block">
          {address.zipcode} {address.city} {address.countryCode}
        </span>
      </address>

      <button onClick={onSelect}>
        {isSelected ? (
          <span className="mr-2 text-lg font-bold text-main">✓</span>
        ) : null}
        {intl.formatMessage({ id: 'CHOOSE' })}
      </button>
      <div className="ml-8">
        <EditAddressModal address={address} />
      </div>
    </div>
  );
}

function AddressBook({ mode, title }) {
  const { data = [] } = useAddressQuery();
  const { data: checkout, isLoading } = useGetCheckout();
  const { mutate } = useSetCheckout();

  return (
    <div className="panel pb-0 shadow">
      <div className="flex flex-col gap-6 border-b border-gray-300 pb-6 text-xl font-bold xl:flex-row xl:items-center">
        <div className="flex-1 text-xl font-bold">{title}</div>
        {mode !== 'billing' ? <CreateAddressModal /> : null}
      </div>

      <div className="grid gap-4 py-4">
        {isLoading && <Loader size="w-10 h-10" />}

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
