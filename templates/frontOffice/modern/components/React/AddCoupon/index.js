import Alert from '../Alert';
import Error from '../Error';
import React from 'react';
import SubmitButton from '../SubmitButton';
import {
  useCouponCreate,
  useCartQuery,
  useCouponClearAll
} from '@openstudio/thelia-api-utils';
import { useForm } from 'react-hook-form';
import { useIntl } from 'react-intl';
import priceFormat from '@js/utils/priceFormat';

export default function AddCoupon() {
  const { register, handleSubmit, formState } = useForm();
  const { data: cart = {} } = useCartQuery();
  const { mutate: create, error, isSuccess } = useCouponCreate();
  const {
    mutate: deleteCoupon,
    isLoading,
    isSuccess: successClear
  } = useCouponClearAll();
  const intl = useIntl();

  return (
    <div className="flex flex-col flex-wrap items-stretch leading-none">
      <form
        className="flex flex-wrap items-center justify-between"
        onSubmit={handleSubmit((values) => {
          create(values.coupon);
        })}
      >
        <div className="items-stret ch flex w-full focus-within:outline focus-within:outline-1 focus-within:outline-main">
          <input
            id="coupon"
            type="text"
            className="h-auto w-full border-main focus:border-main focus:shadow-none focus:outline-none focus:ring-transparent"
            placeholder={intl.formatMessage({ id: 'COUPON' })}
            {...register('coupon', {
              required: intl.formatMessage({ id: 'MANDATORY' })
            })}
          />
          <SubmitButton
            type="submit"
            isSubmitting={formState.isSubmitting}
            className="py-0"
            label={intl.formatMessage({ id: 'OK' })}
          />
        </div>
      </form>
      {formState.errors?.coupon?.message ? (
        <Error error={formState.errors.coupon?.message} />
      ) : null}
      {error ? (
        <Alert type="error" message={error.response?.data?.description} />
      ) : null}
      {isSuccess && !successClear ? (
        <Alert
          type="success"
          title={intl.formatMessage({ id: 'COUPON_ADDED' })}
          message=""
          className="mt-6"
        />
      ) : null}
      {cart?.coupons?.length > 0 ? (
        <div
          className={`${
            isLoading
              ? 'pointer-events-none text-base opacity-20 transition'
              : ''
          } mt-6 border-t-4  bg-gray-100 px-4 py-3 text-sm shadow-md`}
        >
          <span className="block">
            {intl.formatMessage({ id: 'YOU_USING_COUPON' })}
          </span>
          <ul className="my-2">
            {cart?.coupons.map((c) => (
              <li key={c.id} className="block font-bold">
                {c?.code} - {priceFormat(c?.amount)}
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="underline"
            onClick={() => deleteCoupon()}
          >
            {intl.formatMessage({ id: 'CLICK_TO_CLEAR_COUPON' })}
          </button>
        </div>
      ) : null}
    </div>
  );
}
