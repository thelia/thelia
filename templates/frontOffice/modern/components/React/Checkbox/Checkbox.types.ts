import { ChangeEvent } from 'react';
import { ChangeHandler } from 'react-hook-form';

export type CheckboxProps = {
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
};
