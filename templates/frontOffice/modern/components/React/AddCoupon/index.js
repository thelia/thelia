import Alert from '../Alert';
import Error from '../Error';
import Input from '../Input';
import React from 'react';
import SubmitButton from '../SubmitButton';
import { useCouponCreate } from '@openstudio/thelia-api-utils';
import { useForm } from 'react-hook-form';
import { useIntl } from 'react-intl';

export default function AddCoupon() {
  const { register, handleSubmit, formState } = useForm();
  const { mutate: create, error } = useCouponCreate();
  const intl = useIntl();

  return (
    <div className="flex flex-col flex-wrap items-stretch leading-none">
      <form
        className="flex flex-wrap items-center justify-between"
        onSubmit={handleSubmit((values) => {
          create(values.coupon);
        })}
      >
        <label htmlFor="coupon" className="m-0 text-base uppercase">
          {intl.formatMessage({ id: 'COUPON' })}
        </label>
        <div className="flex items-stretch">
          <Input
            id="coupon"
            {...register('coupon', {
              required: intl.formatMessage({ id: 'MANDATORY' })
            })}
            placeholder={intl.formatMessage({ id: 'COUPON' })}
            className="h-auto w-full"
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
        <Alert
          type="error"
          title={error.response?.data?.title}
          message={error.response?.data?.description}
        />
      ) : null}
    </div>
  );
}
