import {
  useAddressQuery,
  useCheckoutCreate
} from '@openstudio/thelia-api-utils';
import { useDispatch, useSelector } from 'react-redux';
import { useEffect, useRef, useState } from 'react';

import React from 'react';
import { setAcceptedTermsAndConditions } from '@js/redux/modules/checkout';
import { useIntl } from 'react-intl';

function createCheckoutResquest(checkout, addressCustomerId) {
  let response = {
    deliveryModuleId: checkout?.deliveryModule?.id,
    paymentModuleId: checkout?.paymentModule?.id,
    billingAddressId: checkout?.billingAddress?.id,
    acceptedTermsAndConditions: checkout?.acceptedTermsAndConditions
  };

  if (
    checkout.deliveryModule &&
    checkout.deliveryModule.deliveryMode === 'pickup'
  ) {
    response.pickupAddress = checkout.deliveryAddress;
    response.billingAddressId = addressCustomerId;
    response.deliveryAddressId = addressCustomerId;
  } else {
    response.deliveryAddressId = checkout?.deliveryAddress?.id;
    response.pickupAddress = null;
  }

  return response;
}

export default function CheckoutBtn() {
  const dispatch = useDispatch();
  const intl = useIntl();
  const { mutate: doCheckout } = useCheckoutCreate();
  const checkout = useSelector((state) => state.checkout);
  const { data: addressesCustomer = [] } = useAddressQuery();
  const [addressCustomerId, setAddressCustomerId] = useState(null);
  const btnRef = useRef(null);

  useEffect(() => {
    if (Array.isArray(addressesCustomer)) {
      const address = addressesCustomer.find((el) => el.default === 1);
      if (address?.id) {
        setAddressCustomerId(address.id);
      }
    }
  }, [addressesCustomer]);

  return (
    <div className="">
      <label className="inline-flex items-start block mb-4">
        <input
          type="checkbox"
          className={`border-gray-300 border text-main focus:border-gray-300 focus:ring-main mt-1`}
          id="validTerms"
          onChange={() => {
            dispatch(setAcceptedTermsAndConditions());
            btnRef?.current?.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
          }}
        />
        <span className="ml-2 leading-0">
          {intl.formatMessage({ id: 'ACCEPT_CGV' })}
        </span>
      </label>

      <button
        className="w-full shadow btn"
        onClick={async () => {
          const request = createCheckoutResquest(checkout, addressCustomerId);
          doCheckout(request);
        }}
        disabled={
          !checkout.deliveryModuleOption ||
          !checkout.paymentModule ||
          !checkout.acceptedTermsAndConditions
        }
      >
        {intl.formatMessage({ id: 'VALIDATE_CHECKOUT' })}
      </button>
    </div>
  );
}
