import { useDispatch, useSelector } from 'react-redux';

import React from 'react';
import Title from '../Title';
import { setMode } from '@redux/modules/checkout';
import { useDeliveryModes } from '@js/api';
import { useIntl } from 'react-intl';

function DeliveryModes() {
	const dispatch = useDispatch();
	const intl = useIntl();
	const selectedMode = useSelector((state) => state.checkout.mode);
	const { data: modes = [] } = useDeliveryModes();
	if (modes.length < 2) return null;

	return (
		<div className="pb-8">
			<Title
				className="text-left"
				title={intl.formatMessage({ id: 'CHOOSE_DELIVERY_MODE' })}
			/>
			<div className="flex flex-col gap-6 xl:flex-row">
				{modes.map((mode, index) => (
					<button
						key={index}
						className={`btn w-full shadow ${
							mode === selectedMode
								? 'bg-main'
								: ' bg-white border-white text-black hover:border-main'
						}`}
						onClick={() => dispatch(setMode(mode))}
					>
						{intl.formatMessage({ id: mode.toUpperCase() })}
					</button>
				))}
			</div>
		</div>
	);
}

export default DeliveryModes;
