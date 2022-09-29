import {
  useDeliveryModulessQuery,
  useGetCheckout,
} from '@openstudio/thelia-api-utils';

export default function useValidDeliveryModules(type) {
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
