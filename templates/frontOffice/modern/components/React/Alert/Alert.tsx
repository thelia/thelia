import React from 'react';
import { ReactComponent as IconInfo } from '@icons/info.svg';
import { AlertProps } from './Alert.types';

export default function Alert({
  type = 'default',
  title,
  message,
  className = ''
}: AlertProps) {
  return (
    <div
      className={`Alert ${type && `Alert--${type}`} ${className}`}
      role="alert"
    >
      <IconInfo className="mb-4 h-6 w-6 text-inherit xs:mb-0" />
      <div className="xs:ml-4">
        {title ? <p className="font-bold">{title}</p> : null}
        {message ? <p className="text-sm">{message}</p> : null}
      </div>
    </div>
  );
}
