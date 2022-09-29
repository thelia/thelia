import React, { Suspense, useEffect, useRef, useState } from 'react';
import { useDebounce } from 'react-use';
import messages, { locale } from '@components/React/intl';
import Loader from '@components/React/Loader';
import { QueryClientProvider } from 'react-query';
import priceFormat from '@utils/priceFormat';
import { queryClient } from '@openstudio/thelia-api-utils';
import { createRoot } from 'react-dom/client';
import { useSearchQuery } from '@openstudio/thelia-api-utils';
import { ReactComponent as IconSearch } from '@icons/search.svg';

import { ReactComponent as IconCLose } from '@icons/close.svg';
import useEscape from '@js/utils/useEscape';
import { trapTabKey } from '@js/standalone/trapItemsMenu';
import { useIntl, IntlProvider } from 'react-intl';

function Item({ title, price, promoPrice, image, url, promo }) {
  return (
    <a href={url} className="CartItem rounded-md bg-white">
      <div className="CartItem-img">
        <img src={image} alt="" loading="lazy" />
      </div>
      <div className="CartItem-contain">
        <strong>{title}</strong>
      </div>
      <div className="flex flex-col items-end text-lg leading-none">
        <span>{promo && priceFormat(promoPrice)}</span>
        <span className={promo ? 'mt-1 text-sm line-through' : ''}>
          {priceFormat(price)}
        </span>
      </div>
    </a>
  );
}

function formatSearchResults(data = []) {
  return data.map((product) => {
    const defaultPSE =
      product?.productSaleElements.find((pse) => pse.default) ||
      product?.productSaleElements[0];
    return {
      id: product.id,
      title: product.i18n.title,
      url: product.url,
      price: defaultPSE.price.taxed,
      promoPrice: defaultPSE.promoPrice.taxed,
      promo: defaultPSE.promo,
      image: product?.images[0]?.id
        ? `/legacy-image-library/product_image_${product?.images[0]?.id}/full/!50,/0/default.webp`
        : ''
    };
  });
}

function SearchResults({ query = null }) {
  const { data = null } = useSearchQuery({
    ref: query,
    title: query,
    limit: 12
  });

  if (!Array.isArray(data)) {
    return null;
  }
  if (data.length === 0) {
    return (
      <div className="mx-auto max-w-xl text-lg font-medium text-white">
        Aucun résultat.
      </div>
    );
  }

  return (
    <>
      <div className="SearchDropdown-results">
        {formatSearchResults(data).map((result) => (
          <Item {...result} key={result.id} />
        ))}
      </div>
      {data.length > 0 && (
        <button
          type="submit"
          className="Button Button--actived mx-auto mt-8 animate-none"
          form="SearchForm"
        >
          Voir tous les résultats
        </button>
      )}
    </>
  );
}

function SearchForm({ formRef, setQuery, query }) {
  const focusRef = useRef(null);

  useEffect(() => {
    focusRef?.current?.focus();
  }, [focusRef]);

  return (
    <form
      id="SearchForm"
      ref={formRef}
      action="/search"
      className="SearchDropdown-form"
      onSubmit={(e) => e.stopPropagation()}
    >
      <label
        htmlFor="SearchInput"
        className="SearchDropdown-field mx-auto max-w-xl"
        aria-label="Recherche"
      >
        <input
          id="SearchInput"
          type="text"
          name="query"
          ref={focusRef}
          value={query}
          required={true}
          placeholder="Recherche"
          className="SearchDropdown-input"
          autoComplete="off"
          onKeyUp={(e) => setQuery(e.target.value)}
          onChange={(e) => setQuery(e.target.value)}
        />
        <IconSearch
          className="SearchDropdown-icon"
          onClick={() => {
            if (query) {
              formRef.current.submit();
            }
          }}
        />
      </label>
    </form>
  );
}

function SearchDropdown({ showResults = false }) {
  const ref = useRef(null);
  const formRef = useRef(null);
  const btnRef = useRef(null);
  const modalRef = useRef(null);
  const [isOpen, setIsOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const intl = useIntl();
  useEscape(ref, () => modalObserver(false));

  const [,] = useDebounce(
    () => {
      setDebouncedQuery(query);
    },
    250,
    [query]
  );

  function modalObserver(open) {
    setIsOpen(open);
    if (open) {
      document.body.classList.add('overflow-y-hidden');
      document.getElementById('StickyToggler').classList.add('searchActive');
    } else {
      btnRef.current.focus();
      document.getElementById('StickyToggler').classList.remove('searchActive');
      document.body.classList.remove('overflow-y-hidden');
    }
  }

  function handleClickModal(target) {
    if (target?.matches('.SearchDropdown-full')) {
      modalObserver(false);
    }
  }

  return (
    <div ref={ref}>
      <div className={`SearchDropdown-contain ${isOpen ? 'opacity-0' : ''}`}>
        <button
          type="button"
          ref={btnRef}
          className="SearchDropdown-fake no-focusTrap"
          aria-label={intl.formatMessage({ id: 'SEARCH' })}
          onClick={() => modalObserver(true)}
          tabIndex="1"
        >
          <IconSearch className="SearchDropdown-fakeIcon" />
          <span className="ml-[10px] hidden lg:block">
            {intl.formatMessage({ id: 'SEARCH' })}
          </span>
        </button>
      </div>
      {isOpen && (
        <div
          className="SearchDropdown-full"
          ref={modalRef}
          tabIndex="0"
          onClick={(e) => handleClickModal(e.target)}
          onKeyDownCapture={(e) => trapTabKey(modalRef.current, e)}
        >
          <button
            type="button"
            className="absolute top-8 right-8 focus:outline-offset-4 focus:outline-white"
            aria-label="Fermer la recherche"
            onClick={() => modalObserver(false)}
          >
            <IconCLose className="pointer-events-none h-4 w-4 text-white" />
          </button>
          <div className="container">
            <SearchForm formRef={formRef} query={query} setQuery={setQuery} />
            {showResults && (
              <div className={!isOpen ? 'hidden' : ''}>
                <Suspense
                  fallback={
                    <div className="col-span-full">
                      <Loader className="mx-auto w-40" />
                    </div>
                  }
                >
                  <div className="trans-up">
                    <SearchResults query={debouncedQuery} />
                  </div>
                </Suspense>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default function SearchDropdownRender() {
  const DOMElement = document.getElementById('SearchDropdown');

  if (!DOMElement) return;

  const root = createRoot(DOMElement);

  root.render(
    <QueryClientProvider client={queryClient}>
      <IntlProvider locale={locale} messages={messages[locale]}>
        <SearchDropdown showResults />
      </IntlProvider>
    </QueryClientProvider>
  );
}
