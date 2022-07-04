import Alert from '../Alert';
import React from 'react';
import Loader from '../Loader';

import { useIntl } from 'react-intl';
import { useValidPaymentModules } from '../Checkout/hooks';
import { useGetCheckout, useSetCheckout } from '@openstudio/thelia-api-utils';

export default function PaymentModules() {
  const intl = useIntl();

  const { data: modules, isLoading } = useValidPaymentModules();

  const { data: checkout } = useGetCheckout();
  const { mutate } = useSetCheckout();

  return (
    <div className="panel shadow">
      <div className="items-center border-b border-gray-300 pb-6 text-xl font-bold">
        {intl.formatMessage({ id: 'PAYMENT_MODE' })}
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

      <div className="divide-y divide-gray-300 divide-opacity-50">
        {modules.map((module) => {
          const isSelected = module.id === checkout?.paymentModuleId;
          return (
            <label key={module.id} className={`block py-6`}>
              <div className="flex items-center">
                {module.images && module.images.length > 0 ? (
                  <div className="mr-4">
                    <img
                      src={module.images[0]?.url}
                      alt=""
                      className="h-12 w-12 bg-white object-contain"
                    />
                  </div>
                ) : null}

                <div className="mr-4">
                  <div className="flex items-center">
                    <input
                      type="radio"
                      className="mr-4 border-2 border-gray-300 text-main focus:border-gray-300 focus:ring-main"
                      checked={isSelected || false}
                      onChange={() => {
                        mutate({
                          ...checkout,
                          paymentModuleId: module.id
                        });
                      }}
                    />
                    <span className="text-lg font-medium">
                      {module?.i18n?.title}
                    </span>
                  </div>
                  {module?.i18n?.chapo ? (
                    <div className={`text-sm`}>{module.i18n.chapo}</div>
                  ) : null}
                </div>
              </div>

              {module?.i18n?.description ? (
                <div
                  className="mt-4"
                  dangerouslySetInnerHTML={{ __html: module.i18n.description }}
                />
              ) : null}
              {module?.i18n?.postscriptum ? (
                <div className="text-xs italic">
                  {module?.i18n?.postscriptum}
                </div>
              ) : null}
            </label>
          );
        })}
      </div>
    </div>
  );
}
