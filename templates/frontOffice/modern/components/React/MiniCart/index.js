import { hideCart, toggleCart } from '@js/redux/modules/visibility';
import messages, { locale } from '../intl';

import { IntlProvider } from 'react-intl';
import MiniCart from './MiniCart';
import { Provider } from 'react-redux';
import { QueryClientProvider } from 'react-query';
import React from 'react';
import { queryClient } from '@js/api';
import { render } from 'react-dom';
import store from '@js/redux/store';

export default function MiniCartRender() {
	const root = document.querySelector('.MiniCart-root');

	if (!root) return;

	document.addEventListener(
		'click',
		(e) => {
			if (e.target?.matches('[data-toggle-cart]')) {
				store.dispatch(toggleCart());
			} else if (e.target?.matches('[data-close-cart]')) {
				store.dispatch(hideCart());
			}
		},
		false
	);

	render(
		<QueryClientProvider client={queryClient}>
			<IntlProvider locale={locale} messages={messages[locale]}>
				<Provider store={store}>
					<MiniCart />
				</Provider>
			</IntlProvider>
		</QueryClientProvider>,
		root
	);
}
