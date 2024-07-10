import Alert from '../Alert';
import React from 'react';
import Loader from '../Loader';

import { useIntl } from 'react-intl';
import useValidPaymentModules from './hooks/useValidPaymentModules';
import { useGetCheckout, useSetCheckout } from '@openstudio/thelia-api-utils';
import { PaymentModule } from '@js/types/common';

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
          {modules.map((module: PaymentModule, index: number) => {
            const isSelected = module.id === checkout?.paymentModuleId;
            return (
              <>
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
                      paymentModuleId: module.id,
                      mustSelectPaymentOption: module?.optionGroups.length > 0,
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
                {isSelected && module?.optionGroups ?
                  <div>
                    {module.optionGroups.map((group) => (
                        <div key={group.code} className={"block bg-gray-100 p-4 rounded-md mt-4"}>
                          {group.options.map((option) => {
                            const optionSelected = option.code === checkout?.paymentOptionChoices.code;
                            return (
                              <label key={option.code} className={'Radio my-3'} htmlFor={`module_option_${option?.code}`}>
                                <input
                                  type="radio"
                                  name="option_radio"
                                  id={`module_option_${option?.code}`}
                                  checked={optionSelected}
                                  onChange={() => {
                                    mutate({
                                      ...checkout,
                                      paymentOptionChoices: option
                                    });
                                  }}
                                />
                                <div>{option?.description}</div>
                              </label>
                            )
                          })}
                        </div>
                      )
                    )
                    }
                  </div> :
                  ''}
              </>
            );
          })}
        </div>
      )}
    </div>
  );
}
