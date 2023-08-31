import { FormattedMessage } from 'react-intl';
import React, { useEffect, useState } from 'react';

import AddressForm from './AddressForm';
import { ReactComponent as IconCLose } from '@icons/close.svg';
import Modal from 'react-modal';
import { useAddressCreate } from '@openstudio/thelia-api-utils';
import { useLockBodyScroll } from 'react-use';
import { ReactComponent as IconPlus } from '@icons/plus.svg';
import Title from '../Title';
import { MutateOptions } from 'react-query';
import { FieldValues } from 'react-hook-form';

export default function CreateAddressModal({ className = '' }) {
  const [showModal] = useState(false);
  useLockBodyScroll(showModal);
  const { mutateAsync: create, isSuccess } = useAddressCreate();
  const [isCreatingAddress, setIsCreatingAddress] = useState(false);

  useEffect(() => {
    if (isSuccess) {
      setIsCreatingAddress(false);
    }
  }, [isSuccess]);

  const submitForm = async (values: {
    variables: FieldValues;
    options?: MutateOptions<any, unknown, any, unknown> | undefined;
  }) => {
    try {
      await create(values);
    } catch (error) {
      throw error;
    }
  };

  return (
    <div className={`${className}`}>
      <button
        className="flex items-center hover:underline"
        type="button"
        onClick={() => {
          setIsCreatingAddress(true);
        }}
      >
        <span className="mr-3 flex h-6 w-6 items-center justify-center rounded-full bg-black text-white">
          <IconPlus className="h-[9px] w-[9px]" />
        </span>

        <FormattedMessage id="CREATE_ADDRESS" />
      </button>
      <Modal
        isOpen={isCreatingAddress}
        onRequestClose={() => setIsCreatingAddress(false)}
        ariaHideApp={false}
        className={{
          base: 'Modal',
          afterOpen: 'Modal--open',
          beforeClose: 'Modal--close'
        }}
        overlayClassName={{
          base: 'Modal-overlay',
          afterOpen: 'opacity-100',
          beforeClose: 'opacity-0'
        }}
        bodyOpenClassName={null}
      >
        <div className="relative">
          <Title title="CREATE_ADDRESS" className="Title--3 mb-8 pr-5" />
          <div className="mx-auto block w-full">
            <button
              type="button"
              className="Modal-close"
              onClick={() => setIsCreatingAddress(false)}
            >
              <IconCLose />
            </button>
            <AddressForm onSubmit={submitForm} />
          </div>
        </div>
      </Modal>
    </div>
  );
}
