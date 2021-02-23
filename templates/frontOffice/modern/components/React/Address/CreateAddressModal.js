import { FormattedMessage, useIntl } from 'react-intl';
import React, { useEffect, useState } from 'react';

import AddressForm from './AddressForm';
import { ReactComponent as CloseIcon } from './imgs/icon-close.svg';
import Modal from 'react-modal';
import { useAddressCreate } from '@js/api';
import { useLockBodyScroll } from 'react-use';

export default function CreateAddressModal({ className = '' }) {
	const [showModal] = useState(false);
	useLockBodyScroll(showModal);
	const { mutate: create, isSuccess } = useAddressCreate();
	const [isCreatingAddress, setIsCreatingAddress] = useState(false);
	const intl = useIntl();

	useEffect(() => {
		if (isSuccess) {
			setIsCreatingAddress(false);
		}
	}, [isSuccess]);

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
					base: 'outline-none bg-white p-8 w-full h-full overflow-auto',
					afterOpen: '',
					beforeClose: ''
				}}
				overlayClassName={{
					base:
						'fixed bg-gray-500 bg-opacity-50 inset-0 z-200 flex items-center justify-center px-4 py-24 lg:px-24',
					afterOpen: '',
					beforeClose: ''
				}}
				bodyOpenClassName={null}
			>
				<div className="relative">
					<div className="flex justify-between items-center">
						<div className="block mx-auto w-full">
							<h4 className="text-3xl mb-8">
								{intl.formatMessage({ id: 'CREATE_ADDRESS' })}
							</h4>
							<button
								type="button"
								className="top-0 right-0 absolute"
								onClick={() => setIsCreatingAddress(false)}
							>
								<CloseIcon />
							</button>
							<AddressForm
								onSubmit={async (values) => {
									try {
										await create(values);
									} catch (error) {
										console.error(error);
									}
								}}
							/>
						</div>
					</div>
				</div>
			</Modal>
		</div>
	);
}
