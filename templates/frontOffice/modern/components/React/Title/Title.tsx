import React from 'react';
import { useIntl } from 'react-intl';
import { TitleProps } from './Title.types';

export default function Title({ title = 'HOME', className }: TitleProps) {
  const intl = useIntl();

  return (
    <div className={`Title ${className || ''}`}>
      {intl.formatMessage({ id: title })}
    </div>
  );
}
