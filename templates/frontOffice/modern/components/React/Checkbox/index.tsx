import React, { ChangeEvent, forwardRef } from 'react';
import { ChangeHandler } from 'react-hook-form';

interface CheckboxProps {
  label: string | JSX.Element;
  name: string;
  id?: string;
  type?: string;
  small?: boolean;
  className?: string;
  description?: string | JSX.Element;
  value?: string | number;
  defaultChecked?: boolean;
  checked?: boolean;
  onChange?: ((e: ChangeEvent<HTMLInputElement>) => void) | ChangeHandler;
}

const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  ({ label, name, small = false, className = '', ...props }, ref) => {
    return (
      <label className={`Checkbox ${className ? className : ''}`}>
        <input type="checkbox" name={name} {...props} ref={ref} />

        <span>{label}</span>
      </label>
    );
  }
);

export default Checkbox;
