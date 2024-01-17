import React, { Suspense } from 'react';
import Title from '../../Title';
import Loader from '../../Loader';
import AddressBook from '../AddressBook';
import { useAddressQuery } from '@openstudio/thelia-api-utils';
import { CheckoutPageType, CheckoutResponse } from '@js/types/checkout.types';

export default function Invoice({
  isVisible,
  checkout,
  page
}: {
  isVisible: boolean;
  checkout?: CheckoutResponse;
  page?: CheckoutPageType;
}) {
  const { data: addresses = [] } = useAddressQuery();
  const title = page ? page.title : '';

  if (!isVisible) return null;
  return (
    <div className="Checkout-page col-span-2">
      <Title title={`${title}`} className="Title--2 mb-8" />
      <Suspense fallback={<Loader />}>
        <AddressBook mode="billing" addresses={addresses} />
      </Suspense>
    </div>
  );
}
