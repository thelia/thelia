import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';

import Alert from '../Alert';
import priceFormat from '@utils/priceFormat';
import { setDeliveryModule } from '@redux/modules/checkout';
import { useIntl } from 'react-intl';
import { useValidDeliveryModules } from '../Checkout/hooks';

function getModuleValidOptions(module) {
	return module?.options?.filter((o) => o.valid) || [];
}

function ModuleOption({ module = {}, option = {}, isSelected }) {
	const intl = useIntl();
	const dispatch = useDispatch();

	return (
		<label className={`block py-6`}>
			<div className="flex flex-wrap items-center xl:flex-nowrap">
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
							onChange={() => dispatch(setDeliveryModule({ module, option }))}
						/>
						<span className="text-lg font-medium">
							{option.title || module?.i18n?.title}
						</span>
					</div>
					{module?.i18n?.chapo ? (
						<div className={`text-sm`}>{module.i18n.chapo}</div>
					) : null}
				</div>

				<div className="w-full mt-2 ml-auto text-2xl font-medium xl:w-auto xl: xl:mt-0 text-main">
					{option.postage
						? `${priceFormat(option.postage)}`
						: intl.formatMessage({ id: 'FREE' })}
				</div>
			</div>

			{module?.i18n?.description ? (
				<div
					className="mt-4"
					dangerouslySetInnerHTML={{ __html: module.i18n.description }}
				/>
			) : null}
			{module?.i18n?.postscriptum ? (
				<div className="text-xs italic">{module?.i18n?.postscriptum}</div>
			) : null}
		</label>
	);
}

export default function DeliveryModules() {
	const intl = useIntl();
	const dispatch = useDispatch();
	const selectedMode = useSelector((state) => state.checkout.mode);
	const {
		deliveryModuleOption,
		deliveryAddress,
		deliveryModule = []
	} = useSelector((state) => state.checkout);
	const modules = useValidDeliveryModules(selectedMode, deliveryAddress?.id);

	// Reset delivery module if not available for the new selected address
	useEffect(() => {
		if (!deliveryModule && modules.length > 0) {
			dispatch(
				setDeliveryModule({ module: modules[0], option: modules[0].options[0] })
			);
		} else if (
			deliveryModule &&
			!modules.find((m) => m.code === deliveryModule.code)
		) {
			dispatch(setDeliveryModule({ module: null, option: null }));
		}
	}, [modules, deliveryModule, dispatch]);

	if (
		modules?.length === 0 ||
		modules?.flatMap(getModuleValidOptions).length === 0
	)
		return (
			<Alert
        title={intl.formatMessage({ id: 'WARNING' })}
        message={intl.formatMessage({ id: 'NO_DELIVERY_MODE_AVAILABLE' })}
        type="warning"
			/>
		);

	return (
		<div className="shadow panel">
			<div className="items-center pb-6 text-xl font-bold border-b border-gray-300">
				{intl.formatMessage({ id: 'CHOOSE_DELIVERY_PROVIDER' })}
			</div>
			<div className="divide-y divide-gray-300 divide-opacity-50">
				{modules.map((module) =>
					getModuleValidOptions(module).map((option) => (
						<ModuleOption
							key={module.code}
							module={module}
							option={option}
							isSelected={
								deliveryModuleOption &&
								deliveryModuleOption?.code === option.code
							}
						/>
					))
				)}
			</div>
		</div>
	);
}
