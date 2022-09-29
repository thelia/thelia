import React, { Suspense } from 'react';
import Title from '../../Title';
import Loader from '../../Loader';
import AddressBook from '../AddressBook';
import { useAddressQuery } from '@openstudio/thelia-api-utils';

export default function Invoice({ isVisible, checkout, page }) {
  const { data: addresses = [] } = useAddressQuery();
  const { title } = page;

  if (!isVisible) return null;
  return (
    <div className="col-span-2 Checkout-page">
      <Title title={`${title}`} className="mb-8 Title--2" />
      <Suspense fallback={<Loader />}>
        <AddressBook mode="billing" addresses={addresses} />
      </Suspense>
    </div>
  )
}
