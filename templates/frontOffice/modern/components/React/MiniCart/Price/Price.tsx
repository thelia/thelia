import priceFormat from '@js/utils/priceFormat';
import { PriceProps } from '../MiniCart.types';
import { useIntl } from 'react-intl';
import { Prices } from '@js/types/common';

function Price({ price, price_promo, isPromo }: PriceProps) {
  return (
    <div className="flex items-center text-lg">
      {isPromo ? (
        <div className="flex flex-col items-end font-bold leading-none">
          <span className="mb-1">{priceFormat(+price_promo)}</span>
          <span className="text-sm font-normal line-through">
            {priceFormat(+price)}
          </span>
        </div>
      ) : (
        <div className="flex flex-col items-end font-bold leading-none">
          {priceFormat(+price)}
        </div>
      )}
    </div>
  );
}

export default Price;
