import React from 'react';
import { useSelector } from 'react-redux';

import Alert from '../Alert';
import Loader from '../Loader';
import priceFormat from '@utils/priceFormat';

import { useIntl } from 'react-intl';
import { useValidDeliveryModules } from '../Checkout/hooks';
import {
  queryClient,
  useGetCheckout,
  useSetCheckout
} from '@openstudio/thelia-api-utils';
import Title from '../Title';

function getModuleValidOptions(module) {
  return module?.options?.filter((o) => o.valid) || [];
}

function ModuleOption({ module = {}, option = {}, isSelected }) {
  const intl = useIntl();

  const { data: checkout } = useGetCheckout();
  const { mutate } = useSetCheckout();

  return (
    <button
      type="button"
      className={`Option ${isSelected ? 'active' : ''}`}
      onClick={() => {
        if (module.deliveryMode === 'delivery') {
          mutate({
            ...checkout,
            deliveryModuleId: module.id,
            deliveryModuleOptionCode: option.code,
            pickupAddress: null
          });
        } else {
          queryClient.setQueryData('checkout', (oldData) => {
            return {
              ...oldData,
              deliveryModuleId: module.id,
              deliveryModuleOptionCode: option.code
            };
          });
        }
      }}
    >
      <div>
        <span className="mr-2 inline-block">
          {option.title || module?.i18n?.title}
        </span>
        <strong>
          (
          {option.postage
            ? `${priceFormat(option.postage)}`
            : intl.formatMessage({ id: 'FREE' })}
          )
        </strong>
      </div>
    </button>
  );
}

export default function DeliveryModules() {
  const intl = useIntl();

  const selectedMode = useSelector((state) => state.checkout.mode);
  const { data: checkout } = useGetCheckout();
  const { data: modules, isLoading } = useValidDeliveryModules(
    selectedMode,
    checkout?.deliveryAddressId
  );

  return (
    <div className="Checkout-block">
      <Title
        title={intl.formatMessage({ id: 'CHOOSE_DELIVERY_PROVIDER' })}
        step={selectedMode === 'delivery' ? 3 : 2}
      />
      <div className="grid gap-6 sm:grid-cols-2">
        {isLoading ? (
          <Loader size="w-10 h-10" className="col-span-2 my-4" />
        ) : (
          (modules?.length === 0 ||
            modules?.flatMap(getModuleValidOptions).length === 0) && (
            <Alert
              title={intl.formatMessage({ id: 'WARNING' })}
              message={intl.formatMessage({ id: 'NO_DELIVERY_MODE_AVAILABLE' })}
              type="warning"
            />
          )
        )}

        {modules.map((module) =>
          getModuleValidOptions(module).map((option) => (
            <ModuleOption
              key={module.code}
              module={module}
              option={option}
              isSelected={
                checkout && checkout?.deliveryModuleOptionCode === option.code
              }
            />
          ))
        )}
      </div>
    </div>
  );
}
