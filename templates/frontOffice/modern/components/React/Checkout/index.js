import { CartItems } from '../MiniCart/MiniCart';
import React, { Suspense } from 'react';
import { useSelector } from 'react-redux';

import AddressBook from './AddressBook';

import CheckoutBtn from './CheckoutBtn';
import DeliveryModes from './DeliveryModes';
import DeliveryModules from './DeliveryModules';
import Loader from '../Loader';
import PaymentModules from './PaymentModules';
import PickupMap from '../PickupMap';
import Title from '../Title';
import { useStep } from './hooks';
import { useCartQuery, useGetCheckout } from '@openstudio/thelia-api-utils';
import { useIntl } from 'react-intl';
import PhoneCheck from '../PhoneCheck';
import { CheckoutFooter } from './CheckoutFooter';
import { ReactComponent as IconBack } from './imgs/icon-back.svg';

function LoadingBlock() {
  return (
    <div className="panel shadow">
      <Loader size="w-12 h-12" />
    </div>
  );
}

function MainContent() {
  const intl = useIntl();
  const { mode: selectedMode, deliveryModuleOption } = useSelector(
    (state) => state.checkout
  );
  const { data: cart = {} } = useCartQuery();
  const { data: checkout } = useGetCheckout();
  const step = useStep(checkout);

  return (
    <>
      <div>
        <DeliveryModes />
        {step >= 2 && (
          <>
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
          </>
        )}
        {step >= 3 && (
          <AddressBook
            mode="billing"
            title={intl.formatMessage({ id: 'CHOOSE_BILLING_ADDRESS' })}
          />
        )}
        {step >= 4 && <PhoneCheck addressId={checkout?.deliveryAddressId} />}
        {step >= 5 && <PaymentModules />}
      </div>
      <CheckoutFooter
        {...cart}
        delivery={deliveryModuleOption?.postage || cart?.delivery}
      />
      {step >= 6 && <CheckoutBtn />}
    </>
  );
}

export default function Checkout() {
  const intl = useIntl();
  const { data: cart = {} } = useCartQuery();

  return (
    <section className="mx-auto max-w-4xl text-gray-700">
      <a
        href="/"
        className="mb-4 inline-flex items-center gap-2 border border-transparent p-1 leading-snug"
      >
        <IconBack className="transform-origin h-4 w-4 rotate-180" />
        <span className="">{intl.formatMessage({ id: 'KEEP_SHOPPING' })}</span>
      </a>
      <div className="panel Checkout-block shadow">
        <div className="flex items-center justify-between">
          <Title title={intl.formatMessage({ id: 'YOUR_ORDER' })} />
        </div>
        <CartItems cart={cart} canDelete={false} evenClass={false} />
      </div>
      <MainContent />
    </section>
  );
}
