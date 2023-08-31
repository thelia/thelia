import React from 'react';
import Title from '../Title';
import { useIntl } from 'react-intl';
import { PaymentProps } from './Payment.types';

export default function Payment() {
  const intl = useIntl();

  return <Title title={intl.formatMessage({ id: 'PAYMENT_MODE' })} />;
}
