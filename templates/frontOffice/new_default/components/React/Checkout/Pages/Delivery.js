import React, { Suspense } from 'react';
import { useSelector } from 'react-redux';
import Title from '../../Title';
import DeliveryModules from '../DeliveryModules';
import DeliveryModes from '../DeliveryModes';
import Loader from '../../Loader';
import AddressBook from '../AddressBook';
import Map from '../../PickupMap';
import { useAddressQuery } from '@openstudio/thelia-api-utils';

export default function Delivery({ isVisible, checkout, page }) {
  const { mode: selectedMode } = useSelector((state) => state.checkout);
  const { data: addresses = [] } = useAddressQuery();
  const { title } = page;

  if (!isVisible) return null;
  return (
    <div className="col-span-2 Checkout-page">
      <Title title={`${title}`} className="mb-8 Title--2" />
      <DeliveryModes />
      {selectedMode === 'delivery' ? (
        <Suspense fallback={<Loader />}>
          <AddressBook
            mode="delivery"
            title='SELECT_DELIVERY_ADDRESS'
            addresses={addresses}
          />
        </Suspense>
      ) : null}
      {selectedMode === 'pickup' ||
        (selectedMode === 'delivery' && checkout?.deliveryAddressId) ? (
        <Suspense fallback={<Loader />}>
          <DeliveryModules />
        </Suspense>
      ) : null}
      {selectedMode === 'pickup' && checkout?.deliveryModuleOptionCode ? (
        <div className="my-8">
          <Map addresses={addresses} />
        </div>
      ) : null}

    </div>
  )
}
