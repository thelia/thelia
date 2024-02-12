import { InputHTMLAttributes } from 'react';

export type InputProps = {
  label: string;
  name: string;
  type?: string;
  error?: any;
  labelClassname?: string;
  className?: string;
  transformValue?: (
    value: InputHTMLAttributes<HTMLInputElement>['value']
  ) => string | InputHTMLAttributes<HTMLInputElement>['value'];
  placeholder?: string;
} & InputHTMLAttributes<HTMLInputElement>;
