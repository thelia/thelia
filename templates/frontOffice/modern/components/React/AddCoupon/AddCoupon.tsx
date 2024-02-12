import Alert from '../Alert';
import Error from '../Error';
import React from 'react';
import {
  useCouponCreate,
  useCartQuery,
  useCouponClearAll,
  useCouponClear
} from '@openstudio/thelia-api-utils';
import { useForm } from 'react-hook-form';
import { useIntl } from 'react-intl';
import priceFormat from '@js/utils/priceFormat';
import { Coupon } from '@js/types/common';

export default function AddCoupon() {
  const { register, handleSubmit, formState } = useForm();
  const { data: cart = {} } = useCartQuery();
  const { mutate: create, error, isSuccess } = useCouponCreate();
  const {
    mutate: clearAllCoupons,
    isLoading,
    isSuccess: successClear
  } = useCouponClearAll();

  const { mutate: clearCoupon } = useCouponClear();

  const intl = useIntl();

  return (
    <div>
      <form
        className={`PhoneCheck mt-0 ${isLoading ? 'PhoneCheck--loading' : ''} ${
          error ? 'PhoneCheck--error' : ''
        }`}
        onSubmit={handleSubmit((values) => {
          create(values.coupon);
        })}
      >
        <div className="PhoneCheck-field">
          <input
            id="coupon"
            type="text"
            className="PhoneInput"
            placeholder={intl.formatMessage({ id: 'COUPON' })}
            {...register('coupon', {
              required: intl.formatMessage({ id: 'MANDATORY' })
            })}
          />
          <button type="submit" className="PhoneCheck-btn">
            {intl.formatMessage({ id: 'OK' })}
          </button>
        </div>
      </form>
      {formState.errors?.coupon?.message ? (
        <Error error={formState.errors.coupon?.message as string} />
      ) : null}
      {error ? (
        <Alert
          type="error"
          className="mt-4"
          message={(error as any).response?.data?.description}
        />
      ) : null}
      {isSuccess && !successClear ? (
        <Alert
          type="success"
          message={intl.formatMessage({ id: 'COUPON_ADDED' })}
          className="mt-4"
        />
      ) : null}
      {cart?.coupons?.length > 0 ? (
        <div
          className={`${
            isLoading
              ? 'pointer-events-none text-base opacity-20 transition'
              : ''
          } mt-4 rounded-sm bg-main-light px-4 py-3 text-sm`}
        >
          <span className="block">
            {cart?.coupons?.length > 1
              ? intl.formatMessage({ id: 'YOU_USING_COUPONS' })
              : intl.formatMessage({ id: 'YOU_USING_COUPON' })}
          </span>
          <ul className="my-2 flex flex-col gap-2">
            {cart?.coupons.map((c: Coupon) => (
              <li key={c.id} className="flex justify-between">
                <span className="block font-bold">
                  {c?.code} - {priceFormat(c?.amount)}
                </span>
                <button
                  className="text-sm text-main-dark hover:text-main"
                  onClick={() => clearCoupon(c.id.toString())}
                >
                  {intl.formatMessage({ id: 'DELETE' })}
                </button>
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="underline"
            onClick={() => clearAllCoupons()}
          >
            {intl.formatMessage({ id: 'CLICK_TO_CLEAR_COUPONS' })}
          </button>
        </div>
      ) : null}
    </div>
  );
}
