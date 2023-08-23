import React, { Ref, forwardRef, useState } from 'react';

import Error from '../Error';
import { ReactComponent as EyeIcon } from '@icons/eye.svg';
import { InputProps } from './Input.types';

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
    }: InputProps,
    ref: Ref<HTMLInputElement>
  ) => {
    const [passwordVisible, setPasswordVisible] = useState(false);

    let finalType = type === 'password' && passwordVisible ? 'text' : type;

    return (
      <label className={`Input ${className ? className : ''}`}>
        {label ? (
          <div
            className={`Input-label  ${
              error ? 'text-error' : ''
            } ${labelClassname}`}
          >
            {label}{' '}
            {props.required ? <span className="text-gray-500">*</span> : ''}
          </div>
        ) : null}
        <div className="relative">
          <input
            placeholder={placeholder}
            ref={ref}
            name={name}
            type={finalType}
            className={`Input-field ${
              error
                ? 'border-error text-error focus:border-error focus:ring-error'
                : 'text-primary'
            }`}
            {...props}
            value={
              transformValue && typeof transformValue === 'function'
                ? transformValue(value)
                : value
            }
          />
          {type === 'password' ? (
            <button
              type="button"
              className={`Input-switchPassword ${
                passwordVisible ? 'is-noVisible' : ''
              }`}
              onClick={() => {
                setPasswordVisible(!passwordVisible);
              }}
            >
              <EyeIcon className="h-5 w-5" />
            </button>
          ) : null}
        </div>

        {error ? (
          <span className="Input-message">
            <Error error={error} />
          </span>
        ) : null}
      </label>
    );
  }
);

export default Input;
