import Alert from '../Alert';
import Error from '../Error';
import Input from '../Input';
import React from 'react';
import SubmitButton from '../SubmitButton';
import { useCouponCreate } from '@js/api';
import { useForm } from 'react-hook-form';
import { useIntl } from 'react-intl';

export default function AddCoupon() {
	const { register, handleSubmit, errors, formState } = useForm();
	const { mutate: create, error } = useCouponCreate();
	const intl = useIntl();
	return (
		<div className="flex flex-col items-stretch flex-wrap leading-none">
			<form
				className="flex justify-between items-center flex-wrap"
				onSubmit={handleSubmit((values) => {
					create(values.coupon);
				})}
			>
				<label htmlFor="coupon" className="text-base uppercase m-0">
					{intl.formatMessage({ id: 'COUPON' })}
				</label>
				<div className="flex items-stretch">
					<Input
						id="coupon"
						name="coupon"
						ref={register({
							required: intl.formatMessage({ id: 'MANDATORY' })
						})}
						placeholder={intl.formatMessage({ id: 'COUPON' })}
						className="w-full h-auto"
					/>
					<SubmitButton
						type="submit"
						isSubmitting={formState.isSubmitting}
						className="py-0"
						label={intl.formatMessage({ id: 'OK' })}
					/>
				</div>
			</form>
			{errors.coupon?.message ? <Error error={errors.coupon?.message} /> : null}
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
