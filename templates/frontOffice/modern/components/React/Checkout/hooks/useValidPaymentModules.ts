import { DeliveryModule, PaymentModule } from '@js/types/common';
import { usePaymentModulessQuery } from '@openstudio/thelia-api-utils';

export default function useValidPaymentModules(type?: any) {
  const { data = [], isLoading } = usePaymentModulessQuery();

  const validModules = (data as DeliveryModule[]).filter((m) => m.valid);

  return {
    data: type
      ? validModules.filter((m) => m.deliveryMode === type)
      : validModules,
    isLoading: isLoading
  };
}
