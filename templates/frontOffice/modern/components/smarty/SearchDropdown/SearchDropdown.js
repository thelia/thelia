import React, { Suspense, useEffect, useRef, useState } from 'react';
import { useClickAway, useDebounce } from 'react-use';

import Loader from '@components/React/Loader';
import { QueryClientProvider } from 'react-query';
import priceFormat from '@utils/priceFormat';
import { queryClient } from '@openstudio/thelia-api-utils';
import { render } from 'react-dom';
import { useSearchQuery } from '@openstudio/thelia-api-utils';

function Item({ title, price, promoPrice, image, url }) {
  return (
    <a href={url} className="flex items-center px-4 py-2 hover:bg-gray-200">
      <div>
        <img
          src={image}
          className="object-contain w-12 h-12 rounded-full"
          alt=""
          loading="lazy"
        />
      </div>
      <div className="px-4 font-bold">{title}</div>
      <div className="ml-auto font-bold">
        <span>{promoPrice > 0 && priceFormat(promoPrice)}</span>
        <span className={promoPrice > 0 ? 'line-through ml-1 text-sm' : ''}>
          {priceFormat(price)}
        </span>
      </div>
    </a>
  );
}

function formatSearchResults(data = []) {
  return data.map((product) => ({
    id: product.id,
    title: product.i18n.title,
    url: product.url,
    price: product?.productSaleElements[0]?.price.taxed,
    promoPrice: product?.productSaleElements[0]?.promoPrice.taxed,
    // priceUntaxed: product?.productSaleElements[0]?.price.untaxed,
    image: product?.images[0]?.url
  }));
}

function SearchResults({ query }) {
  const { data = [] } = useSearchQuery(query);

  if (!Array.isArray(data)) {
    return null;
  }
  if (data.length <= 0) {
    return <div className="p-4">NO RESULT</div>;
  }

  return formatSearchResults(data).map((result) => (
    <Item {...result} key={result.id} />
  ));
}

function SearchDropdown({ showResults = false }) {
  const ref = useRef(null);
  const formRef = useRef(null);
  const [isOpen, setIsOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');

  useClickAway(ref, () => {
    setIsOpen(false);
  });

  const [,] = useDebounce(
    () => {
      setDebouncedQuery(query);
    },
    250,
    [query]
  );

  useEffect(() => {
    if (debouncedQuery) {
      setIsOpen(true);
    } else {
      setIsOpen(false);
    }
  }, [debouncedQuery, setIsOpen]);

  return (
    <div ref={ref}>
      <div className="flex">
        <form
          ref={formRef}
          className="flex-1 hidden md:block"
          onSubmit={(e) => e.stopPropagation()}
        >
          <input
            type="text"
            name="query"
            value={query}
            className="block leading-normal appearance-none SearchDropdown-input focus:outline-none"
            autoComplete="off"
            onKeyUp={(e) => setQuery(e.target.value)}
            onChange={(e) => setQuery(e.target.value)}
            onFocus={() => {
              if (debouncedQuery) {
                setIsOpen(true);
              }
            }}
          />
          <svg
            onClick={() => {
              if (query) {
                formRef.current.submit();
              }
            }}
            className="w-4 h-4 mx-auto cursor-pointer fill-current SearchDropdown-icon"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 64 64"
          >
            <path
              d="M80.114,90.929H77.189l-1.1-1.1a22.891,22.891,0,0,0,5.851-15.36A23.771,23.771,0,1,0,58.171,98.243a22.891,22.891,0,0,0,15.36-5.851l1.1,1.1v2.926L92.914,114.7l5.486-5.486Zm-21.943,0A16.457,16.457,0,1,1,74.629,74.471,16.389,16.389,0,0,1,58.171,90.929Z"
              transform="translate(-34.4 -50.7)"
            />
          </svg>
        </form>
      </div>

      {showResults ? (
        <div
          className={`absolute w-full py-2 SearchDropdown-results top-full ${
            !isOpen ? 'hidden' : ''
          }`}
        >
          <div className="overflow-y-auto bg-white divide-y divide-gray-200 rounded shadow-lg max-h-half-screen">
            <Suspense
              fallback={
                <div className="p-4">
                  <Loader size="w-8 h-8" />
                </div>
              }
            >
              <SearchResults query={debouncedQuery} />
            </Suspense>
          </div>
        </div>
      ) : null}
    </div>
  );
}

export default function SearchDropdownRender() {
  const root = document.querySelector('.SearchDropdown');
  if (!root) return;
  render(
    <QueryClientProvider client={queryClient}>
      <SearchDropdown showResults />
    </QueryClientProvider>,
    root
  );
}
