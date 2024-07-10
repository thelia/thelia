import {
  useAddressDelete,
  useGetCheckout,
  useSetCheckout
} from '@openstudio/thelia-api-utils';

import CreateAddressModal from '../Address/CreateAddressModal';
import EditAddressModal from '../Address/EditAddressModal';
import { useIntl } from 'react-intl';
import { AddressMode, Address as AddressType } from './type';
import Title from '../Title/Title';
import Loader from '../Loader/Loader';
import { CheckoutRequest } from '@js/types/checkout.types';

function Address({
  address,
  onSelect = () => {},
  isSelected
}: {
  address: AddressType;
  onSelect: () => void;
  isSelected: boolean;
}) {
  const { isSuccess: deleteSuccess } = useAddressDelete();
  const intl = useIntl();

  if (deleteSuccess) return null;

  return (
    <label
      htmlFor={`home_delivery_address_${address?.id}`}
      className={`AddressCard ${isSelected ? 'AddressCard--selected' : ''}`}
    >
      <div className="Radio">
        <input
          type="radio"
          checked={isSelected}
          name="home_delivery_address"
          id={`home_delivery_address_${address?.id}`}
          onChange={onSelect}
        />
      </div>
      <address className="AddressCard-info">
        <span className="mb-3 font-bold text-black">
          {address.label}{' '}
          {address.isDefault && (
            <>({intl.formatMessage({ id: 'DEFAULT_ADDRESS' })})</>
          )}
        </span>
        <div>
          {address.civilityTitle?.short} {address.firstName} {address.lastName}
        </div>
        <span className="street-address block">{address.address1}</span>
        {address.address2 ? (
          <span className="street-address">
            {address.address2}
            {address.address3 ? ' - ' + address.address3 : null}
          </span>
        ) : null}
        <span className="postal-code">
          {address.zipCode} {address.city} {address.countryCode}
        </span>
      </address>
      <EditAddressModal address={address} />
    </label>
  );
}

function AddressBook({
  mode,
  title,
  addresses
}: {
  mode: AddressMode;
  title?: string;
  addresses: AddressType[];
}) {
  const { data: checkout, isLoading } = useGetCheckout();
  const { mutate } = useSetCheckout();

  return (
    <div className="mt-8">
      {title && <Title className="Title--3 mb-5 text-2xl" title={title} />}
      <div className="grid gap-4">
        {isLoading && <Loader className="w-40" />}

        {addresses.map((address) => {
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
                let request: Partial<CheckoutRequest> = {};

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
        <CreateAddressModal />
      </div>
    </div>
  );
}

export default AddressBook;
