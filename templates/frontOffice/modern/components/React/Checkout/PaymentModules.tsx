import Alert from '../Alert';
import React from 'react';
import Loader from '../Loader';

import { useIntl } from 'react-intl';
import useValidPaymentModules from './hooks/useValidPaymentModules';
import { useGetCheckout, useSetCheckout } from '@openstudio/thelia-api-utils';
import { DeliveryModule, PaymentModule } from '@js/types/common';

export default function PaymentModules() {
  const intl = useIntl();

  const { data: modules, isLoading } = useValidPaymentModules();

  const { data: checkout } = useGetCheckout();
  const { mutate } = useSetCheckout();

  return (
    <div className="mb-8">
      {isLoading ? (
        <Loader />
      ) : modules?.length === 0 ? (
        <Alert
          title={intl.formatMessage({ id: 'WARNING' })}
          message={intl.formatMessage({ id: 'NO_PAYMENT_MODE_AVAILABLE' })}
          type="warning"
        />
      ) : (
        <div className="flex-start item-start mt-8 flex flex-col gap-3">
          {modules.map((module: DeliveryModule, index: number) => {
            const isSelected = module.id === checkout?.paymentModuleId;
            return (
              <label
                key={index}
                htmlFor={`option_${module?.code}`}
                className="Radio"
              >
                <input
                  type="radio"
                  name="radio"
                  id={`option_${module?.code}`}
                  checked={isSelected}
                  onChange={() => {
                    mutate({
                      ...checkout,
                      paymentModuleId: module.id
                    });
                  }}
                />
                <span
                  className={`mr-6 block text-base ${
                    isSelected ? 'text-main' : ''
                  }`}
                >
                  {module?.i18n?.title}{' '}
                  <span className="block text-xs text-gray-600">
                    {module?.i18n?.chapo}
                  </span>
                </span>
              </label>
            );
          })}
        </div>
      )}
    </div>
  );
}
