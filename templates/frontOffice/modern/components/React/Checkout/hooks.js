import {
  useDeliveryModulessQuery,
  useGetCheckout,
  usePaymentModulessQuery
} from '@openstudio/thelia-api-utils';

export function useValidDeliveryModules(type) {
  const { data: checkout, isLoading } = useGetCheckout();
  const { data = [], isLoading: isDeliveryModuleLoading } =
    useDeliveryModulessQuery(checkout?.deliveryAddressId);

  const validDeliveryModules = data.filter(
    (m) => m.valid && m.options?.length > 0
  );

  return {
    data: type
      ? validDeliveryModules.filter((m) => m.deliveryMode === type)
      : validDeliveryModules,
    isLoading: isLoading || isDeliveryModuleLoading
  };
}

export function useValidPaymentModules(type) {
  const { data = [], isLoading } = usePaymentModulessQuery();

  const validModules = data.filter((m) => m.valid);

  return {
    data: type
      ? validModules.filter((m) => m.deliveryMode === type)
      : validModules,
    isLoading: isLoading
  };
}
