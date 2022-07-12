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
import Title from '../Title';

function Address({ address = {}, onSelect = () => {}, isSelected }) {
  const { isSuccess: deleteSuccess } = useAddressDelete();
  const intl = useIntl();

  if (deleteSuccess) return null;

  return (
    <div className="flex w-full items-center justify-between">
      <address className="mr-auto">
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

      <button
        onClick={onSelect}
        className={` px-4 py-2 focus:outline focus:outline-2 focus:outline-main ${
          isSelected ? 'bg-main text-white' : 'bg-gray-200'
        }`}
      >
        {isSelected ? <span className="mr-2 text-lg font-bold">✓</span> : null}
        {intl.formatMessage({ id: isSelected ? 'CHOOSEN' : 'CHOOSE' })}
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
    <div className="Checkout-block">
      <div className="flex items-center justify-between gap-6 text-xl font-bold ">
        <Title title={title} step={mode === 'delivery' ? 2 : 4} />
      </div>
      <div className="panel pb-0 shadow">
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
          <div className="flex justify-end">
            {mode !== 'billing' ? <CreateAddressModal /> : null}
          </div>
        </div>
      </div>
    </div>
  );
}

export default AddressBook;
