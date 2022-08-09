import Alert from '../Alert';
import React from 'react';
import Loader from '../Loader';

import { useIntl } from 'react-intl';
import { useValidPaymentModules } from '../Checkout/hooks';
import { useGetCheckout, useSetCheckout } from '@openstudio/thelia-api-utils';
import Title from '../Title';

export default function PaymentModules() {
  const intl = useIntl();

  const { data: modules, isLoading } = useValidPaymentModules();

  const { data: checkout } = useGetCheckout();
  const { mutate } = useSetCheckout();

  return (
    <div className="mb-10 lg:mb-16">
      <Title title={intl.formatMessage({ id: 'PAYMENT_MODE' })} step={6} />
      <div>
        {isLoading ? (
          <Loader size="w-10 h-10" />
        ) : (
          modules?.length === 0 && (
            <Alert
              title={intl.formatMessage({ id: 'WARNING' })}
              message={intl.formatMessage({ id: 'NO_PAYMENT_MODE_AVAILABLE' })}
              type="warning"
            />
          )
        )}
      </div>

      <div className="grid gap-6 sm:grid-cols-2">
        {modules.map((module) => {
          const isSelected = module.id === checkout?.paymentModuleId;
          return (
            <button
              key={module.id}
              type="button"
              className={`Option ${isSelected ? 'active' : ''}`}
              onClick={() => {
                mutate({
                  ...checkout,
                  paymentModuleId: module.id
                });
              }}
            >
              {module.title || module?.i18n?.title}
            </button>
          );
        })}
      </div>
    </div>
  );
}
