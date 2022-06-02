import React, { forwardRef } from 'react';

import Error from '../Error';

const Select = forwardRef(
  ({ label, name, options = [], error, className = '', ...props }, ref) => {
    return (
      <label className={`${className ? className : 'block w-full'}`}>
        {label ? (
          <div
            className={`text-sm font-bold ${
              error ? 'text-red-500' : 'text-gray-700'
            } `}
          >
            {label}
          </div>
        ) : null}

        <select
          ref={ref}
          name={name}
          {...props}
          value={props.defaultValue || props.value}
          className="mt-0 block w-full border-0 border-b-2 border-gray-200 px-0.5 focus:border-black focus:ring-0"
        >
          {options.map((option, index) => {
            return (
              <option
                key={index}
                className={option.className}
                value={option.value}
              >
                {option.label}
              </option>
            );
          })}
        </select>
        {error ? (
          <div className="mt-1">
            <Error error={error} />
          </div>
        ) : null}
      </label>
    );
  }
);

export default Select;
