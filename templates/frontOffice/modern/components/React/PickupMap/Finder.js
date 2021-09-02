import '@reach/combobox/styles.css';
import './Finder.css';

import {
  Combobox,
  ComboboxInput,
  ComboboxList,
  ComboboxOption,
  ComboboxPopover
} from '@reach/combobox';
import React, { useState } from 'react';

import Input from '../Input';
import { useDebounce } from 'react-use';
import { useFindAddress } from '@openstudio/thelia-api-utils';

function formatAddress(address) {
  if (!address) return '';

  return `${address.house_number || ''} ${address.road || ''} ${
    address.village || address.town || address.city || ''
  }, ${address.postcode || ''}, ${address.state || ''}, ${
    address.country || ''
  }`;
}

export default function Finder({ onFind, defaultValue = '' }) {
  const [address, setAddress] = useState(defaultValue);
  const [val, setVal] = useState(defaultValue);

  const handleSearchTermChange = (event) => {
    setVal(event.target.value);
  };

  useDebounce(
    () => {
      setAddress(val);
    },
    500,
    [val]
  );

  const { data = [] } = useFindAddress(address);

  return (
    <div>
      <Combobox
        aria-labelledby="address"
        className="p-4 bg-white rounded-t"
        onSelect={(value) => {
          setVal(value);
          onFind(value);
        }}
      >
        <ComboboxInput
          as={Input}
          onChange={handleSearchTermChange}
          placeholder="Enter your address"
          value={val}
          transformValue={(value) =>
            value.address ? formatAddress(value.address) : value
          }
        />
        {data && (
          <ComboboxPopover>
            {data.length > 0 ? (
              <ComboboxList className="PickupMapFinder">
                {data.map((result) => {
                  const str = formatAddress(result.address);
                  return (
                    <ComboboxOption key={result.place_id} value={result}>
                      {str}
                    </ComboboxOption>
                  );
                })}
              </ComboboxList>
            ) : null}
          </ComboboxPopover>
        )}
      </Combobox>
    </div>
  );
}
