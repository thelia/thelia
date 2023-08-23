import React from 'react';
import { ErrorProps } from './Error.types';

export default function Error({ error }: ErrorProps) {
  if (!error) return null;

  return <span className="Input-message">{error}</span>;
}
