import { useDispatch, useSelector } from 'react-redux';

import Alert from '../Alert';
import React from 'react';
import { setPaymentModule } from '@redux/modules/checkout';
import { useIntl } from 'react-intl';
import { useValidPaymentModules } from '../Checkout/hooks';

export default function PaymentModules() {
	const intl = useIntl();
	const selectedModuleId = useSelector(
		(state) => state.checkout?.paymentModule?.id
	);
	const dispatch = useDispatch();
	const modules = useValidPaymentModules();

	if (modules?.length === 0)
		return (
			<Alert
        title={intl.formatMessage({ id: 'WARNING' })}
        message={intl.formatMessage({ id: 'NO_PAYMENT_MODE_AVAILABLE' })}
				type="warning"
			/>
		);

	return (
		<div className="shadow panel">
			<div className="items-center pb-6 text-xl font-bold border-b border-gray-300">
				{intl.formatMessage({ id: 'PAYMENT_MODE' })}
			</div>

			<div className="divide-y divide-gray-300 divide-opacity-50">
				{modules.map((module) => {
					const isSelected = selectedModuleId === module.id;
					return (
						<label key={module.id} className={`block py-6`}>
							<div className="flex items-center">
								{module.images && module.images.length > 0 ? (
									<div className="mr-4">
										<img
											src={module.images[0]?.url}
											alt=""
											className="object-contain w-12 h-12 bg-white"
										/>
									</div>
								) : null}

								<div className="mr-4">
									<div className="flex items-center">
										<input
											type="radio"
											className="mr-4 border-2 border-gray-300 text-main focus:border-gray-300 focus:ring-main"
											checked={isSelected || false}
											onChange={() => dispatch(setPaymentModule(module))}
										/>
										<span className="text-lg font-medium">
											{module?.i18n?.title}
										</span>
									</div>
									{module?.i18n?.chapo ? (
										<div className={`text-sm`}>{module.i18n.chapo}</div>
									) : null}
								</div>
							</div>

							{module?.i18n?.description ? (
								<div
									className="mt-4"
									dangerouslySetInnerHTML={{ __html: module.i18n.description }}
								/>
							) : null}
							{module?.i18n?.postscriptum ? (
								<div className="text-xs italic">
									{module?.i18n?.postscriptum}
								</div>
							) : null}
						</label>
					);
				})}
			</div>
		</div>
	);
}
