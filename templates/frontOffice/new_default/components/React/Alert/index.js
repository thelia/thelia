import React from 'react';
import { ReactComponent as IconInfo } from '@icons/info.svg';

export default function Alert({ type = 'default', title, message, className = '' }) {
  return (
    <div className={`Alert ${type && `Alert--${type}`} ${className}`} role="alert">
      <IconInfo className="w-6 h-6 mb-4 text-inherit xs:mb-0" />
      <div className="xs:ml-4">
        {title ? <p className="font-bold">{title}</p> : null}
        {message ? <p className="text-sm">{message}</p> : null}
      </div>
    </div>
  );
}
