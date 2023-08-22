import React, { forwardRef } from 'react';

import Error from '../Error';
import { SelectProps } from './Select.types';

const Select = forwardRef<HTMLSelectElement, SelectProps>(
  (
    { label, name, options = [], error, className = '', ...props }: SelectProps,
    ref
  ) => {
    return (
      <label className={`Select ${className ? className : ''}`}>
        {label ? (
          <div className={`Select-label ${error ? 'text-error' : ''} `}>
            {label}{' '}
            {props.required ? <span className="text-gray-500">*</span> : ''}
          </div>
        ) : null}

        <select
          ref={ref}
          name={name}
          {...props}
          defaultValue={props.defaultValue}
          className={`Select-field ${
            error
              ? 'border-error text-error focus:border-error focus:ring-error'
              : ''
          }`}
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
