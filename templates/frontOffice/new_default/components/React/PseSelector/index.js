import React, { useEffect, useMemo, useState } from 'react';

import { addToCart } from '@openstudio/thelia-api-utils';
import { isEqual } from 'lodash-es';
import priceFormat from '@utils/priceFormat';
import { queryClient } from '@openstudio/thelia-api-utils';
import { createRoot } from 'react-dom/client';
import { showCart } from '@js/redux/modules/visibility';
import store from '@js/redux/store';
import Quantity from '../Quantity';
import Alert from '../Alert';
import { useIntl } from 'react-intl';
import messages, { locale } from '@components/React/intl';
import { IntlProvider } from 'react-intl';

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
            <div key={attribute.id} className="mb-5">
              <div className="text-sm text-gray-600">{attribute.title}</div>
              <div className="flex flex-wrap gap-2 mt-3">
                {attribute.values.map((attrAv) => {
                  return (
                    <button
                      key={attrAv.id}
                      className={`PseSelector-value ${
                        currentCombinationValues.includes(attrAv.id)
                          ? 'PseSelector-value--checked'
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
  const intl = useIntl();
  if (!pse) {
    return (
      <div className="mb-4 text-xl font-bold text-red-500">
        {intl.formatMessage({ id: 'INVALID_COMBINATION' })}
      </div>
    );
  }
  return (
    <div className="flex flex-col items-end justify-start lg:ml-auto">
      <span className="text-3xl font-medium leading-none">
        {priceFormat(pse.isPromo ? pse.promoPrice : pse.price)}
      </span>
      {pse.isPromo ? (
        <span className="leading-none line-through">
          {priceFormat(pse.price)}
        </span>
      ) : null}
      <span className="text-xs text-gray-600">
        {intl.formatMessage({ id: 'INCLUDING_TAXES' })}
      </span>
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
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [quantity, setQuantity] = useState(currentPse?.quantity || 1);
  const intl = useIntl();

  async function addPseToCart({ pseId, quantity = 1 }) {
    if (!pseId) return;
    setError(false);
    setLoading(true);
    try {
      const response = await addToCart({
        pseId,
        quantity
      });
      queryClient.setQueryData('cart', response.cart);
      store.dispatch(showCart());
    } catch (error) {
      setError(true);
    }
    setLoading(false);
  }

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

  useEffect(() => {
    document.getElementById('RefPse').innerText = currentPse?.ref;
  }, [currentPse]);

  return (
    <div className={`py-8 ${loading ? 'pointer-events-none opacity-50' : ''}`}>
      <AttributeSelector
        attributes={attributes}
        currentCombination={currentCombination}
        setAttributes={setCurrentCombination}
      />
      <div className="flex flex-wrap items-end justify-between gap-4 lg:gap-8">
        <Quantity
          mutate={setQuantity}
          max={currentPse?.quantity || 0}
          quantity={quantity}
          title={true}
        />
        <PriceDisplay pse={currentPse} />
        {!currentPse?.quantity || currentPse?.quantity <= 0 ? (
          <span className="PseSelector-stock PseSelector-stock--error">
            {intl.formatMessage({ id: 'OUT_OF_STOCK' })}
          </span>
        ) : (
          <span className="PseSelector-stock PseSelector-stock--success">
            {intl.formatMessage({ id: 'IN_STOCK' })}
          </span>
        )}
      </div>

      {currentPse && currentPse?.quantity && currentPse?.quantity > 0 ? (
        <button
          className="w-full mt-8 Button lg:mt-7 2xl:w-auto"
          onClick={() => {
            addPseToCart({
              pseId: currentPse.id,
              quantity
            });
          }}
          data-toggle-cart
        >
          Ajouter au panier
        </button>
      ) : null}

      {error && (
        <Alert
          type="warning"
          className="mt-4"
          title={intl.formatMessage({ id: 'ERROR_ADD_CART' })}
          message={intl.formatMessage({ id: 'TRY_LATER' })}
        />
      )}
    </div>
  );
}

export default function PseSelectorRoot() {
  const DOMElement = document.getElementById('PseSelector-root');

  if (!DOMElement) return;

  const root = createRoot(DOMElement);

  root.render(
    <IntlProvider locale={locale} messages={messages[locale]}>
      <PseSelector pses={window.PSES} attributes={window.ATTRIBUTES} />
    </IntlProvider>
  );
}
