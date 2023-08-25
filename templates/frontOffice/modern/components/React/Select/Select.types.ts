import { SelectHTMLAttributes } from 'react';

export type SelectProps = {
  name?: string;
  options?: { value: string; label: string; className?: string }[];
  label?: string;
  error?: string;
  placeholder?: string;
  defaultValue?: string | string[] | undefined;
  required?: boolean;
  disabled?: boolean;
  id?: string;
  className?: string;
  fieldClassName?: string;
} & SelectHTMLAttributes<HTMLSelectElement>;
