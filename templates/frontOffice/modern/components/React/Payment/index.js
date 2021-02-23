import React from 'react';
import Title from '../Title';
import { useIntl } from 'react-intl';

export default function Payment({ checkout = {} }) {
	const intl = useIntl();

	return <Title title={intl.formatMessage({ id: 'PAYMENT_MODE' })} />;
}
