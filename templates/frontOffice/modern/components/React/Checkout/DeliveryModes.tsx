import {
  useDeliveryModes,
  useSetCheckout,
  useGetCheckout
} from '@openstudio/thelia-api-utils';
import { useIntl } from 'react-intl';
import Alert from '../Alert';
import Loader from '../Loader';
import { useGlobalCheckout } from '@js/state/checkout';

function DeliveryModes() {
  const intl = useIntl();

  const { checkoutState, actions } = useGlobalCheckout();
  const selectedMode = checkoutState.mode;

  const { data: checkout } = useGetCheckout();
  const { mutate } = useSetCheckout();
  const { data: modes = [], isLoading } = useDeliveryModes();

  return isLoading ? (
    <Loader className="mx-auto mt-8 w-40" />
  ) : modes.length === 0 ? (
    <Alert
      type="warning"
      title={intl.formatMessage({ id: 'ERROR' })}
      message={intl.formatMessage({ id: 'NO_DELIVERY_MODULE_MESSAGE' })}
    />
  ) : (
    <div className="mb-8 grid gap-5 xs:grid-cols-2">
      {Array.isArray(modes) &&
        modes.map((mode, index) => (
          <button
            key={index}
            className={`fon-medium rounded-md p-4 text-center outline-main  ${
              mode === selectedMode ? 'bg-main-light' : 'bg-gray-100'
            }`}
            onClick={() => {
              actions.setMode(mode);
              mutate({
                ...checkout,
                deliveryAddressId: null,
                deliveryModuleId: null,
                deliveryModuleOptionCode: '',
                pickupAddress: null
              });
            }}
          >
            {intl.formatMessage({ id: mode.toUpperCase() })}
          </button>
        ))}
    </div>
  );
}

export default DeliveryModes;
