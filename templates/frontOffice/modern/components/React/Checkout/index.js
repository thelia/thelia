import { CartItems, MiniCartFooter } from '../MiniCart/MiniCart';
import React, { Suspense, useState } from 'react';
import { useSelector } from 'react-redux';

import AddressBook from './AddressBook';

import CheckoutBtn from './CheckoutBtn';
import { ReactComponent as CloseIcon } from '@icons/drop-down.svg';
import DeliveryModes from './DeliveryModes';
import DeliveryModules from './DeliveryModules';
import Loader from '../Loader';
import PaymentModules from './PaymentModules';
import PickupMap from '../PickupMap';
import Title from '../Title';

import { useCartQuery, useGetCheckout } from '@openstudio/thelia-api-utils';
import { useIntl } from 'react-intl';
import PhoneCheck from '../PhoneCheck';

function LoadingBlock() {
  return (
    <div className="panel shadow">
      <Loader size="w-12 h-12" />
    </div>
  );
}

function MainContent() {
  const intl = useIntl();

  const { mode: selectedMode } = useSelector((state) => state.checkout);
  const { data: checkout } = useGetCheckout();

  return (
    <div>
      <div className="">
        <DeliveryModes />

        <Suspense fallback={<LoadingBlock />}>
          {selectedMode === 'delivery' ? (
            <AddressBook
              mode="delivery"
              title={intl.formatMessage({ id: 'CHOOSE_DELIVERY_ADDRESS' })}
            />
          ) : null}
        </Suspense>

        <Suspense fallback={<LoadingBlock />}>
          {selectedMode === 'pickup' ||
          (selectedMode === 'delivery' && checkout?.deliveryAddressId) ? (
            <DeliveryModules />
          ) : null}
        </Suspense>

        {selectedMode === 'pickup' && checkout?.deliveryModuleOptionCode ? (
          <div className="my-8">
            <PickupMap />
          </div>
        ) : null}

        <AddressBook
          mode="billing"
          title={intl.formatMessage({ id: 'CHOOSE_BILLING_ADDRESS' })}
        />

        <PhoneCheck addressId={checkout?.deliveryAddressId} />
      </div>
    </div>
  );
}

function Sidebar() {
  const intl = useIntl();
  const { data: cart = {} } = useCartQuery();
  const [cartOpen, setCartOpen] = useState(true);
  const { deliveryModuleOption } = useSelector((state) => state.checkout);
  return (
    <div className="">
      <div className="panel shadow">
        <div className="flex items-center justify-between">
          <Title
            title={intl.formatMessage({ id: 'YOUR_ORDER' })}
            className="mb-0 text-center"
          />
          <button
            onClick={() => setCartOpen(!cartOpen)}
            className="flex items-center"
          >
            <span className={cartOpen ? 'invisible' : ''}>View cart</span>
            <CloseIcon
              className={`ml-4 h-4 w-4 transform transition-transform ${
                cartOpen ? 'rotate-0' : '-rotate-90'
              }`}
            />
          </button>
        </div>
        <div
          className={`overflow-y-hidden ${cartOpen ? 'max-h-full' : 'max-h-0'}`}
        >
          <CartItems cart={cart} canDelete={false} />
        </div>
      </div>
      <div
        className="sticky"
        style={{ top: 'calc(var(--Header-height) + 1.5rem)' }}
      >
        <div className="panel shadow">
          <MiniCartFooter
            {...cart}
            delivery={deliveryModuleOption?.postage || cart?.delivery}
          />
        </div>

        <PaymentModules />

        <div className="mb-20 xl:mb-0">
          <CheckoutBtn />
        </div>
      </div>
    </div>
  );
}

export default function Checkout() {
  return (
    <div className="grid gap-6 xl:grid-cols-3">
      <div className="xl:col-span-2">
        <MainContent />
      </div>
      <Sidebar />
    </div>
  );
}
