import { useDeliveryModulessQuery, usePaymentModulessQuery } from '@js/api';

import { useSelector } from 'react-redux';

export function useValidDeliveryModules(type) {
	const { deliveryAddress } = useSelector((state) => state.checkout);
	const { data = [] } = useDeliveryModulessQuery(deliveryAddress?.id);

	const validDeliveryModules = data.filter(
		(m) => m.valid && m.options?.length > 0
	);

	return type
		? validDeliveryModules.filter((m) => m.deliveryMode === type)
		: validDeliveryModules;
}

export function useValidPaymentModules(type) {
	const { data = [] } = usePaymentModulessQuery();

	const validModules = data.filter((m) => m.valid);

	return type
		? validModules.filter((m) => m.deliveryMode === type)
		: validModules;
}
