import React, { useEffect, useMemo, useState } from 'react';

import { addToCart } from '@js/api';
import { isEqual } from 'lodash-es';
import priceFormat from '@utils/priceFormat';
import { queryClient } from '../../../assets/js/api';
import { render } from 'react-dom';
import { showCart } from '@js/redux/modules/visibility';
import store from '@js/redux/store';

async function addPseToCart({ pseId, quantity = 1 }) {
	if (!pseId) return;

	try {
		const data = await addToCart({
			pseId,
			quantity
		});
		queryClient.setQueryData('cart', data.cart);
		store.dispatch(showCart());
	} catch (error) {}
}

function AttributeSelector({
	attributes = [],
	currentCombination = {},
	setAttributes = () => {}
}) {
	const currentCombinationValues = useMemo(() => {
		return Object.values(currentCombination);
	}, [currentCombination]);

	if (!attributes || !Array.isArray(attributes)) return null;

	return (
		<div>
			{attributes
				.filter((attribute) => attribute?.values.length > 1)
				.map((attribute) => {
					return (
						<div key={attribute.id} className="mb-4">
							<div className="text-lg font-bold">{attribute.title}</div>
							<div className="flex gap-2 mt-2">
								{attribute.values.map((attrAv) => {
									return (
										<button
											key={attrAv.id}
											className={`btn btn--sm ${
												currentCombinationValues.includes(attrAv.id)
													? 'bg-main-dark'
													: ''
											}`}
											onClick={() => {
												const newCombination = {
													...currentCombination,
													[attribute.id]: attrAv.id
												};

												setAttributes(newCombination);
											}}
										>
											{attrAv.label}
										</button>
									);
								})}
							</div>
						</div>
					);
				})}
		</div>
	);
}

function PriceDisplay({ pse }) {
	if (!pse)
		return (
			<div className="text-red-500 text-xl font-bold">
				Cette combinaison n'existe pas
			</div>
		);

	if (!pse.quantity || pse.quantity <= 0) {
		return (
			<div className="text-yellow-500 text-xl font-bold">
				Produit hors stock
			</div>
		);
	}
	return (
		<div>
			<span className="font-bold text-xl">
				{priceFormat(pse.isPromo ? pse.promoPrice : pse.price)}
			</span>
			{pse.isPromo ? (
				<span className="line-through ml-4">{priceFormat(pse.price)}</span>
			) : null}
		</div>
	);
}

function PseSelector({ pses = [], attributes = [] }) {
	const defaultPseCombination = useMemo(() => {
		const defaultPse = pses.find((pse) => pse.isDefault);
		return defaultPse.combination;
	}, [pses]);

	const [currentCombination, setCurrentCombination] = useState(
		defaultPseCombination
	);
	const [currentPse, setCurrentPse] = useState(null);

	useEffect(() => {
		const matchingPSE = pses.find((pse) => {
			return isEqual(pse.combination, currentCombination);
		});

		if (matchingPSE) {
			setCurrentPse(matchingPSE);
		} else {
			setCurrentPse(null);
		}
	}, [currentCombination, pses, setCurrentPse]);

	return (
		<div>
			<AttributeSelector
				attributes={attributes}
				currentCombination={currentCombination}
				setAttributes={setCurrentCombination}
			/>
			<div className="mt-8 border-t pt-8">
				<PriceDisplay pse={currentPse} />
			</div>
			<div className="mt-8">
				<button
					className="btn"
					disabled={
						!currentPse || !currentPse.quantity || currentPse.quantity <= 0
					}
					onClick={() => {
						addPseToCart({
							pseId: currentPse.id
						});
					}}
				>
					Ajouter au panier
				</button>
			</div>
		</div>
	);
}

export default function PseSelectorRoot() {
	const root = document.getElementById('PseSelector-root');
	if (!root) return;

	render(
		<PseSelector pses={window.PSES} attributes={window.ATTRIBUTES} />,
		root
	);
}
