import {
  useDeliveryModulessQuery,
  useGetCheckout,
  usePaymentModulessQuery
} from '@openstudio/thelia-api-utils';

export function useValidDeliveryModules(type) {
  const { data: checkout } = useGetCheckout();
  const { data = [] } = useDeliveryModulessQuery(checkout?.deliveryAddressId);

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
