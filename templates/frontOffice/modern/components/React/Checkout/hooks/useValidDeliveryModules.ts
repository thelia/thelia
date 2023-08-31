import {
  useDeliveryModulessQuery,
  useGetCheckout
} from '@openstudio/thelia-api-utils';
import { DeliveryModule } from '@js/types/common';

export default function useValidDeliveryModules(type: string) {
  const { data: checkout, isLoading } = useGetCheckout();
  const { data = [], isLoading: isDeliveryModuleLoading } =
    useDeliveryModulessQuery(checkout?.deliveryAddressId);

  const validDeliveryModules = (data as DeliveryModule[]).filter(
    (m) => m.valid && m.options?.length > 0
  );

  return {
    data: type
      ? validDeliveryModules.filter((m) => m.deliveryMode === type)
      : validDeliveryModules,
    isLoading: isLoading || isDeliveryModuleLoading
  };
}
