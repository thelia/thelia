import messages, { locale } from '@components/React/intl';

import Checkout from '@components/React/Checkout';
import { IntlProvider } from 'react-intl';
import { Provider } from 'react-redux';
import { QueryClientProvider } from 'react-query';
import React from 'react';
import { queryClient } from '@js/api';
import { render } from 'react-dom';
import store from '@redux/store';

function CheckoutWrapper() {
	return (
		<QueryClientProvider client={queryClient}>
			<IntlProvider locale={locale} messages={messages[locale]}>
				<Provider store={store}>
					<Checkout />
				</Provider>
			</IntlProvider>
		</QueryClientProvider>
	);
}

export default function CheckoutPage() {
	const root = document.getElementById('Checkout');
	if (!root) return;

	render(<CheckoutWrapper />, root);
}
