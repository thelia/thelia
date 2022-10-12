import {
  usePaymentModulessQuery
} from '@openstudio/thelia-api-utils';

export default function useValidPaymentModules(type) {
  const { data = [], isLoading } = usePaymentModulessQuery();

  const validModules = data.filter((m) => m.valid);

  return {
    data: type
      ? validModules.filter((m) => m.deliveryMode === type)
      : validModules,
    isLoading: isLoading
  };
}
