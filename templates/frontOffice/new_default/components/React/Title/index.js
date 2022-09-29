import React from 'react';
import { useIntl } from 'react-intl';

export default function Title({ title = 'HOME', className }) {
  const intl = useIntl();

  return (
    <div className={`Title ${className || ''}`}>
      {intl.formatMessage({ id: title })}
    </div>
  );
}
