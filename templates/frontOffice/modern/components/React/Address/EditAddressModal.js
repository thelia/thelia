import React, { useEffect, useState } from 'react';

import AddressForm from './AddressForm';
import { ReactComponent as CloseIcon } from './imgs/icon-close.svg';
import { ReactComponent as IconPen } from './imgs/icon-pen.svg';
import Modal from 'react-modal';
import { useAddressUpdate } from '@openstudio/thelia-api-utils';
import { useIntl } from 'react-intl';
import { useLockBodyScroll } from 'react-use';

export default function EditAddress({ address = {} }) {
  const intl = useIntl();
  const [isEditingAddress, setIsEditingAddress] = useState(false);
  useLockBodyScroll(isEditingAddress);
  const { mutateAsync: update, isSuccess } = useAddressUpdate();

  useEffect(() => {
    if (isSuccess) {
      setIsEditingAddress(false);
    }
  }, [isSuccess]);

  const submitForm = async (values) => {
    await update({
      id: address.id,
      data: values
    });
  };

  return (
    <div className="">
      <div
        className="flex items-center hover:underline"
        onClick={() => setIsEditingAddress(true)}
        type="button"
      >
        <IconPen className="mr-4 h-4 w-4 fill-current" />
        {intl.formatMessage({ id: 'EDIT' })}
      </div>
      {isEditingAddress ? (
        <Modal
          isOpen={isEditingAddress}
          onRequestClose={() => setIsEditingAddress(false)}
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
            <button
              type="button"
              className="absolute top-0 right-0"
              onClick={() => setIsEditingAddress(false)}
            >
              <CloseIcon />
            </button>
            <div className="mx-auto block w-full">
              <h4 className=" mb-8 text-3xl">
                {intl.formatMessage({ id: 'EDIT_AN_ADDRESS' })}
              </h4>
              <AddressForm address={address} onSubmit={submitForm} />
            </div>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}
