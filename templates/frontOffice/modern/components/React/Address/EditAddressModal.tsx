import React, { useEffect, useState } from 'react';

import AddressForm from './AddressForm';
import { ReactComponent as IconCLose } from '@icons/close.svg';
import Modal from 'react-modal';
import { useAddressUpdate } from '@openstudio/thelia-api-utils';
import { useIntl } from 'react-intl';
import { useLockBodyScroll } from 'react-use';
import Title from '../Title';
import { Address } from '../Checkout/type';
import { FieldValues } from 'react-hook-form';

export default function EditAddress({ address }: { address: Address }) {
  const intl = useIntl();
  const [isEditingAddress, setIsEditingAddress] = useState(false);
  useLockBodyScroll(isEditingAddress);
  const { mutateAsync: update, isSuccess } = useAddressUpdate();

  useEffect(() => {
    if (isSuccess) {
      setIsEditingAddress(false);
    }
  }, [isSuccess]);

  const submitForm = async (values: FieldValues) => {
    await update({
      id: address.id,
      data: values
    });
  };

  return (
    <div className="AddressCard-edit">
      <button onClick={() => setIsEditingAddress(true)} type="button">
        {intl.formatMessage({ id: 'EDIT' })}
      </button>
      {isEditingAddress ? (
        <Modal
          isOpen={isEditingAddress}
          onRequestClose={() => setIsEditingAddress(false)}
          ariaHideApp={false}
          shouldFocusAfterRender={true}
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
            <button
              type="button"
              className="Modal-close"
              onClick={() => setIsEditingAddress(false)}
            >
              <IconCLose />
            </button>
            <div className="mx-auto block w-full">
              <Title title="EDIT_AN_ADDRESS" className="Title--3 mb-8 pr-5" />
              <AddressForm address={address} onSubmit={submitForm} />
            </div>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}
