import { useIntl } from 'react-intl';
import AddCoupon from '../AddCoupon';
import priceFormat from '@js/utils/priceFormat';
import React from 'react';
import { Cart } from '@js/types/common';

export default function Recap({
  cart,
  small = false
}: {
  cart: Cart;
  small?: boolean;
}) {
  const { delivery, taxes, discount, coupon, total } = cart;
  const intl = useIntl();
  return (
    <div className={`Recap ${small ? 'Recap--small' : ''}`}>
      <div className={small ? '' : 'w-1/2'}>
        <AddCoupon />
      </div>
      <div className="Recap-grid">
        <FooterItem
          label={intl.formatMessage({ id: 'TOTAL_UNTAXED' })}
          value={priceFormat(total - taxes)}
        />
        {delivery !== null ? (
          <FooterItem
            label={intl.formatMessage({ id: 'DELIVERY_FOOTER' })}
            value={
              delivery <= 0
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
      <div className="Recap-total">
        <span className="">{intl.formatMessage({ id: 'TOTAL' })}</span>
        <span className="text-2xl font-bold">
          {total + delivery - discount <= 0
            ? intl.formatMessage({ id: 'FREE' })
            : priceFormat(total + delivery - discount)}
        </span>
      </div>
    </div>
  );
}

function FooterItem({
  label = '',
  value = 0
}: {
  label?: string;
  value?: number | string;
}) {
  return (
    <div className="Recap-item">
      <span>{label}</span>
      <strong className="font-bold">{value}</strong>
    </div>
  );
}
