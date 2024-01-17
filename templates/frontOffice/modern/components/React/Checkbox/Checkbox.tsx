import React, { forwardRef } from 'react';
import { CheckboxProps } from './Checkbox.types';

const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  (
    { label, name, small = false, className = '', ...props }: CheckboxProps,
    ref
  ) => {
    return (
      <label className={`Checkbox ${className ? className : ''}`}>
        <input type="checkbox" name={name} {...props} ref={ref} />

        <span>{label}</span>
      </label>
    );
  }
);

export default Checkbox;
