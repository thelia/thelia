import { CartItems, MiniCartFooter } from '../MiniCart/MiniCart';
import React, { Suspense, useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';

import AddressBook from './AddressBook';
import Alert from '../Alert';
import CheckoutBtn from './CheckoutBtn';
import { ReactComponent as CloseIcon } from '@icons/drop-down.svg';
import DeliveryModes from './DeliveryModes';
import DeliveryModules from './DeliveryModules';
import ErrorBoundary from '../ErrorBoundary';
import Loader from '../Loader';
import PaymentModules from './PaymentModules';
import PickupMap from '../PickupMap';
import Title from '../Title';
import { setDeliveryAddress } from '@redux/modules/checkout';
import { useAddressQuery } from '../../../assets/js/api';
import { useCartQuery } from '@js/api';
import { useIntl } from 'react-intl';

function LoadingBlock() {
	return (
		<div className="shadow panel">
			<Loader size="w-12 h-12" />
		</div>
	);
}

function Checkout() {
	const intl = useIntl();
	const dispatch = useDispatch();
	const { data: addresses = [] } = useAddressQuery();
	const defaultAddress = [...addresses].find((a) => a.default);
	const {
		deliveryAddress,
		deliveryModule,
		deliveryModuleOption,
		mode: selectedMode
	} = useSelector((state) => state.checkout);

	useEffect(() => {
		if (selectedMode === 'delivery' && !deliveryAddress && defaultAddress) {
			dispatch(setDeliveryAddress({ ...defaultAddress }));
		}
	}, [selectedMode, deliveryAddress, defaultAddress, dispatch]);

	return (
		<div>
			<div className="">
				<DeliveryModes />

				<Suspense fallback={<LoadingBlock />}>
					{selectedMode === 'delivery' ? (
						<AddressBook
							mode="delivery"
							title={intl.formatMessage({ id: 'CHOOSE_DELIVERY_ADDRESS' })}
						/>
					) : null}
				</Suspense>

				<Suspense fallback={<LoadingBlock />}>
					{selectedMode === 'pickup' ||
					(selectedMode === 'delivery' && deliveryAddress) ? (
						<DeliveryModules />
					) : null}
				</Suspense>

				{selectedMode === 'pickup' ? (
					<div className="my-8">
						<PickupMap module={deliveryModule} />
					</div>
				) : null}

				<Suspense fallback={<LoadingBlock />}>
					{deliveryAddress && deliveryModuleOption ? (
						<AddressBook
							mode="billing"
							title={intl.formatMessage({ id: 'CHOOSE_BILLING_ADDRESS' })}
						/>
					) : null}
				</Suspense>
			</div>
		</div>
	);
}

function CheckoutRender() {
	const intl = useIntl();
	const { data: cart = {} } = useCartQuery();
	const [cartOpen, setCartOpen] = useState(true);
	const { deliveryModuleOption } = useSelector((state) => state.checkout);

	return (
		<div className="grid gap-6 xl:grid-cols-3">
			<div className="xl:col-span-2">
				<Checkout />
			</div>

			<div className="">
				<div className="shadow panel">
					<div className="flex items-center justify-between">
						<Title
							title={intl.formatMessage({ id: 'YOUR_ORDER' })}
							className="mb-0 text-center"
						/>
						<button
							onClick={() => setCartOpen(!cartOpen)}
							className="flex items-center"
						>
							<span className={cartOpen ? 'invisible' : ''}>View cart</span>
							<CloseIcon
								className={`w-4 h-4 ml-4 transform transition-transform ${
									cartOpen ? 'rotate-0' : '-rotate-90'
								}`}
							/>
						</button>
					</div>
					<div
						className={`overflow-y-hidden ${
							cartOpen ? 'max-h-full' : 'max-h-0'
						}`}
					>
						<CartItems cart={cart} canDelete={false} />
					</div>
				</div>
				<div
					className="sticky"
					style={{ top: 'calc(var(--Header-height) + 1.5rem)' }}
				>
					<div className="shadow panel">
						<MiniCartFooter
							{...cart}
							delivery={deliveryModuleOption?.postage || cart.delivery}
						/>
					</div>

					<Suspense fallback={<LoadingBlock />}>
						<PaymentModules />
					</Suspense>

					<div className="mb-20 xl:mb-0">
						<CheckoutBtn />
					</div>
				</div>
			</div>
		</div>
	);
}

export default function CheckoutRenderWrapper() {
	return (
		<ErrorBoundary
			fallback={
				<Alert type="error" title="Erreur" message="Un problème est survenu" />
			}
		>
			<Suspense
				fallback={
					<Loader
						size=" w-12 md:w-20  h-12 md:h-20"
						className="absolute inset-x-0 p-8"
					/>
				}
			>
				<CheckoutRender />
			</Suspense>
		</ErrorBoundary>
	);
}
