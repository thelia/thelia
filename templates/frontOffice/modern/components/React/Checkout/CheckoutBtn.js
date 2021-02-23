import { useAddressQuery, useCheckoutCreate } from '../../../assets/js/api';
import { useEffect, useState } from 'react';

import React from 'react';
import { useIntl } from 'react-intl';
import { useSelector } from 'react-redux';

function createCheckoutResquest(checkout, addressCustomerId) {
	let response = {
		deliveryModuleId: checkout?.deliveryModule?.id,
		paymentModuleId: checkout?.paymentModule?.id,
		billingAddressId: checkout?.billingAddress?.id
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
	const intl = useIntl();
	const { mutate: doCheckout } = useCheckoutCreate();
	const checkout = useSelector((state) => state.checkout);
	const { data: addressesCustomer = [] } = useAddressQuery();
	const [addressCustomerId, setAddressCustomerId] = useState(null);

	useEffect(() => {
		const address = addressesCustomer.find((el) => el.default === 1);
		setAddressCustomerId(address.id);
	}, [addressesCustomer]);

	return (
		<div className="text-center">
			<button
				className="w-full shadow btn"
				onClick={async () => {
					const request = createCheckoutResquest(checkout, addressCustomerId);
					doCheckout(request);
				}}
				disabled={!checkout.deliveryModuleOption || !checkout.paymentModule}
			>
				{intl.formatMessage({ id: 'VALIDATE_CHECKOUT' })}
			</button>
		</div>
	);
}
