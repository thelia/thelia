import React, { Suspense } from 'react';

import Title from '../../Title';
import DeliveryModules from '../DeliveryModules';
import DeliveryModes from '../DeliveryModes';
import Loader from '../../Loader';
import AddressBook from '../AddressBook';
import Map from '../../PickupMap';
import { useAddressQuery } from '@openstudio/thelia-api-utils';
import { CheckoutPageType, CheckoutResponse } from '@js/types/checkout.types';
import { useGlobalCheckout } from '@js/state/checkout';

export default function Delivery({
  isVisible,
  checkout,
  page
}: {
  isVisible: boolean;
  checkout?: CheckoutResponse;
  page?: CheckoutPageType;
}) {
  const { checkoutState } = useGlobalCheckout();
  const { mode: selectedMode } = checkoutState;

  const { data: addresses = [] } = useAddressQuery();
  const title = page ? page.title : '';

  if (!isVisible) return null;
  return (
    <div className="Checkout-page col-span-2">
      <Title title={`${title}`} className="Title--2 mb-8" />
      <DeliveryModes />
      {selectedMode === 'delivery' ? (
        <Suspense fallback={<Loader />}>
          <AddressBook
            mode="delivery"
            title="SELECT_DELIVERY_ADDRESS"
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
  );
}
