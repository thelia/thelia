import getLocale from '@utils/getLocale';
let fn = null;

export default function priceFormat(price, options = {}) {
  const locale = options.locale || getLocale();
  const currency = options.currency || global.DEFAULT_CURRENCY_CODE;

  if (typeof price !== 'number' || !locale || !currency) return '';

  if (!fn) {
    fn = new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency
    });
  }

  return fn.format(price);
}
