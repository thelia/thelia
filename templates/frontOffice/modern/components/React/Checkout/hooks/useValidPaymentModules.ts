import { DeliveryModule, PaymentModule } from '@js/types/common';
import { usePaymentModulessQuery } from '@openstudio/thelia-api-utils';

export default function useValidPaymentModules() {
  const { data = [], isLoading } = usePaymentModulessQuery();

  const validModules = (data as PaymentModule[]).filter((m) => m.valid);

  return {
    data: validModules,
    isLoading: isLoading
  };
}
