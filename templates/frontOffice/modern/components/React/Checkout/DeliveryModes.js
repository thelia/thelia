import { useDispatch, useSelector } from 'react-redux';

import React from 'react';
import Title from '../Title';
import { setMode } from '@redux/modules/checkout';
import {
  useDeliveryModes,
  useSetCheckout,
  useGetCheckout
} from '@openstudio/thelia-api-utils';
import { useIntl } from 'react-intl';

function DeliveryModes() {
  const dispatch = useDispatch();
  const intl = useIntl();
  const selectedMode = useSelector((state) => state.checkout.mode);
  const { data: checkout } = useGetCheckout();
  const { mutate } = useSetCheckout();
  const { data: modes = [] } = useDeliveryModes();
  if (modes.length < 2) return null;

  return (
    <div className="Checkout-block">
      <Title
        title={intl.formatMessage({ id: 'CHOOSE_DELIVERY_MODE' })}
        step={1}
      />
      <div className="flex w-full flex-col gap-6 sm:flex-row">
        {Array.isArray(modes) &&
          modes.map((mode, index) => (
            <button
              key={index}
              className={`Option ${mode === selectedMode ? 'active' : ''}`}
              onClick={() => {
                dispatch(setMode(mode));
                mutate({
                  ...checkout,
                  deliveryAddressId: null,
                  deliveryModuleId: null,
                  deliveryModuleOptionCode: '',
                  pickupAddress: null
                });
              }}
            >
              {intl.formatMessage({ id: mode.toUpperCase() })}
            </button>
          ))}
      </div>
    </div>
  );
}

export default DeliveryModes;
