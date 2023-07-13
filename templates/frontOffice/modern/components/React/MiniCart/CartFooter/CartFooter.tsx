import { FooterItemProps, MiniCartFooterProps } from '../MiniCart.types';
import { useIntl } from 'react-intl';
import priceFormat from '@js/utils/priceFormat';
import AddCoupon from '@components/React/AddCoupon';
import Total from '../Total/Total';

function FooterItem({ label, value }: FooterItemProps) {
  return (
    <dl className="flex items-center justify-between text-lg uppercase leading-none">
      <dt>{label}</dt>
      <dd>{value}</dd>
    </dl>
  );
}

export default function CartFooter({
  delivery,
  taxes,
  discount,
  coupon,
  total
}: MiniCartFooterProps) {
  const intl = useIntl();

  return (
    <div>
      <div className="grid gap-y-4 border-t border-b border-main border-opacity-25 py-5">
        <AddCoupon />
        <FooterItem
          label={intl.formatMessage({ id: 'TOTAL_UNTAXED' })}
          value={priceFormat(total - taxes)}
        />
        {delivery !== null ? (
          <FooterItem
            label={intl.formatMessage({ id: 'DELIVERY' })}
            value={priceFormat(delivery)}
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
      <Total
        label={intl.formatMessage({ id: 'TOTAL' })}
        value={priceFormat(total + delivery)}
      />
    </div>
  );
}
