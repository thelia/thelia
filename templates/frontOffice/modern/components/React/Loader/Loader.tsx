import React from 'react';
import { ReactComponent as IconLoader } from '@icons/loader.svg';
import { LoaderProps } from './Loader.types';

export default function Loader({
  color = '#76b82a',
  className = 'w-40'
}: LoaderProps) {
  return (
    <div className={`${className} mx-auto block`}>
      <IconLoader className="h-6 w-6" />
    </div>
  );
}
