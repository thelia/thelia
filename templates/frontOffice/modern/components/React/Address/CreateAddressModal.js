import { FormattedMessage, useIntl } from 'react-intl';
import React, { useEffect, useState } from 'react';

import AddressForm from './AddressForm';
import { ReactComponent as CloseIcon } from './imgs/icon-close.svg';
import Modal from 'react-modal';
import { useAddressCreate } from '@openstudio/thelia-api-utils';
import { useLockBodyScroll } from 'react-use';

export default function CreateAddressModal({ className = '' }) {
  const [showModal] = useState(false);
  useLockBodyScroll(showModal);
  const { mutateAsync: create, isSuccess } = useAddressCreate();
  const [isCreatingAddress, setIsCreatingAddress] = useState(false);
  const intl = useIntl();

  useEffect(() => {
    if (isSuccess) {
      setIsCreatingAddress(false);
    }
  }, [isSuccess]);

  const submitForm = async (values) => {
    await create(values);
  };

  return (
    <div className={`${className}`}>
      <button
        className="btn btn--sm"
        type="button"
        onClick={() => {
          setIsCreatingAddress(true);
        }}
      >
        <FormattedMessage id="CREATE_ADDRESS" />
      </button>
      <Modal
        isOpen={isCreatingAddress}
        onRequestClose={() => setIsCreatingAddress(false)}
        ariaHideApp={false}
        className={{
          base: 'h-full w-full overflow-auto bg-white p-8 outline-none',
          afterOpen: '',
          beforeClose: ''
        }}
        overlayClassName={{
          base: 'fixed bg-gray-500 bg-opacity-50 inset-0 z-200 flex items-center justify-center px-4 py-24 lg:px-24',
          afterOpen: '',
          beforeClose: ''
        }}
        bodyOpenClassName={null}
      >
        <div className="relative">
          <div className="flex items-center justify-between">
            <div className="mx-auto block w-full">
              <h4 className="mb-8 text-3xl">
                {intl.formatMessage({ id: 'CREATE_ADDRESS' })}
              </h4>
              <button
                type="button"
                className="absolute top-0 right-0"
                onClick={() => setIsCreatingAddress(false)}
              >
                <CloseIcon />
              </button>
              <AddressForm onSubmit={submitForm} />
            </div>
          </div>
        </div>
      </Modal>
    </div>
  );
}
