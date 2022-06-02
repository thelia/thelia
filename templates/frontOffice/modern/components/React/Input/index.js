import React, { forwardRef } from 'react';

import Error from '../Error';

const Input = forwardRef(
  (
    {
      label,
      name,
      type = 'text',
      error,
      labelClassname = '',
      className = '',
      transformValue,
      placeholder,
      value,
      ...props
    },
    ref
  ) => {
    return (
      <label className={`${className ? className : 'block w-full'}`}>
        {label ? (
          <div
            className={`text-sm font-bold ${
              error ? 'text-red-500' : 'text-gray-700'
            } ${labelClassname}`}
          >
            {label}
          </div>
        ) : null}
        <input
          placeholder={placeholder}
          ref={ref}
          name={name}
          type={type}
          className={`mt-0 block w-full border-0 border-b-2 px-0.5 focus:border-black focus:ring-0 ${
            error ? 'border-red-500' : 'border-gray-200'
          }`}
          {...props}
          value={
            transformValue && typeof transformValue === 'function'
              ? transformValue(value)
              : value
          }
        />

        {error ? (
          <div className="mt-1">
            <Error error={error} />
          </div>
        ) : null}
      </label>
    );
  }
);

export default Input;
