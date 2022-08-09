import { useIntl } from 'react-intl';
import AddCoupon from '../AddCoupon';
import priceFormat from '@js/utils/priceFormat';
import React from 'react';

export function CheckoutFooter({ delivery, taxes, discount, coupon, total }) {
  const intl = useIntl();
  return (
    <div className="panel mt-10 mb-0 shadow lg:mt-16">
      <AddCoupon />
      <div className="my-6 grid gap-y-4">
        <FooterItem
          label={intl.formatMessage({ id: 'TOTAL_UNTAXED' })}
          value={priceFormat(total - taxes)}
        />
        {delivery !== null ? (
          <FooterItem
            label={intl.formatMessage({ id: 'DELIVERY_FOOTER' })}
            value={
              delivery >= 0
                ? intl.formatMessage({ id: 'FREE' })
                : priceFormat(delivery)
            }
          />
        ) : null}
        <FooterItem
          label={intl.formatMessage({ id: 'TAXES' })}
          value={priceFormat(taxes)}
        />
        {discount && coupon !== 'NO_COUPON' ? (
          <FooterItem
            label={intl.formatMessage({ id: 'DISCOUNT' })}
            value={priceFormat(discount)}
          />
        ) : null}
      </div>
      <div className="flex items-center justify-between">
        <span className="text-2xl uppercase">
          {intl.formatMessage({ id: 'TOTAL' })}
        </span>
        <span className="text-3xl font-bold">
          {total + delivery - discount <= 0
            ? intl.formatMessage({ id: 'FREE' })
            : priceFormat(total + delivery - discount)}
        </span>
      </div>
    </div>
  );
}

function FooterItem({ label = '', value = 0 }) {
  return (
    <div className="flex items-center justify-between gap-4 text-xl">
      <span>{label}</span>
      <strong className="font-bold">{value}</strong>
    </div>
  );
}
