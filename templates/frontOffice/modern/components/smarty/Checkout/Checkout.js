import Checkout from '@components/React/Checkout';
import { RawIntlProvider } from 'react-intl';
import { Provider } from 'react-redux';
import { QueryClientProvider } from 'react-query';
import React from 'react';
import { queryClient } from '@openstudio/thelia-api-utils';
import { createRoot } from 'react-dom/client';
import store from '@redux/store';
import intl from '@components/React/intl';

function CheckoutWrapper() {
  return (
    <QueryClientProvider client={queryClient}>
      <RawIntlProvider value={intl}>
        <Provider store={store}>
          <Checkout />
        </Provider>
      </RawIntlProvider>
    </QueryClientProvider>
  );
}

export default function CheckoutPage() {
  const DOMElement = document.getElementById('Checkout');

  if (!DOMElement) return;

  const root = createRoot(DOMElement);

  root.render(<CheckoutWrapper />);
}
