import { hideCart, toggleCart } from '@js/redux/modules/visibility';

import { RawIntlProvider } from 'react-intl';
import MiniCart from './MiniCart';
import { Provider } from 'react-redux';
import { QueryClientProvider } from 'react-query';
import React from 'react';
import { queryClient } from '@openstudio/thelia-api-utils';
import { createRoot } from 'react-dom/client';
import store from '@js/redux/store';
import intl from '../intl';

export default function MiniCartRender() {
  const DOMElement = document.querySelector('.MiniCart-root');

  if (!DOMElement) return;

  const root = createRoot(DOMElement);

  document.addEventListener(
    'click',
    (e) => {
      if ((e.target as HTMLElement)?.matches('[data-toggle-cart]')) {
        store.dispatch(toggleCart());
      } else if ((e.target as HTMLElement)?.matches('[data-close-cart]')) {
        store.dispatch(hideCart());
      }
    },
    false
  );

  root.render(
    <QueryClientProvider client={queryClient}>
      <RawIntlProvider value={intl}>
        <Provider store={store}>
          <MiniCart />
        </Provider>
      </RawIntlProvider>
    </QueryClientProvider>
  );
}
