import Checkbox from '../Checkbox';
import Input from '../Input';
import React from 'react';
import Select from '../Select';
import SubmitButton from '../SubmitButton';
import { useForm } from 'react-hook-form';
import { useIntl } from 'react-intl';

export default function AddressForm({ address = {}, onSubmit = () => {} }) {
	const intl = useIntl();
	const { register, handleSubmit, errors, formState } = useForm();
	const titles = (window.CUSTOMER_TITLES || [])
		.map((t) => {
			return {
				label: t.short,
				value: t.id,
				isDefault: !!t.isDefault
			};
		})
		.sort((a, b) => b.isDefault - a.isDefault);

	const countries = (window.COUNTRIES || []).map((c) => {
		return {
			label: c.title,
			value: c.id
		};
	});

	return (
		<form
			className="grid grid-cols-1 gap-6 mb-0"
			onSubmit={handleSubmit(onSubmit)}
		>
			<Input
				label={intl.formatMessage({ id: 'LABEL_LABEL' })}
				name="label"
				defaultValue={address.label}
				ref={register({ required: 'Mandatory' })}
				error={errors.label?.message}
			/>
			<Select
				label={intl.formatMessage({ id: 'CIVILITY_TITLE_LABEL' })}
				name="civilityTitle.id"
				defaultValue={address.title}
				options={titles}
				ref={register({ required: 'Mandatory' })}
				error={errors.title?.message}
			/>
			<Input
				label={intl.formatMessage({ id: 'FIRSTNAME_LABEL' })}
				name="firstName"
				defaultValue={address.firstName}
				ref={register({ required: 'Mandatory' })}
				error={errors.firstName?.message}
			/>
			<Input
				label={intl.formatMessage({ id: 'LASTNAME_LABEL' })}
				name="lastName"
				defaultValue={address.lastName}
				ref={register({ required: 'Mandatory' })}
				error={errors.lastName?.message}
			/>
			<Input
				label={intl.formatMessage({ id: 'COMPANY_LABEL' })}
				name="company"
				defaultValue={address.company}
				ref={register({ required: 'Mandatory' })}
				error={errors.company?.message}
			/>

			<Input
				label={intl.formatMessage({ id: 'ADDRESS_1_LABEL' })}
				name="address1"
				defaultValue={address.address1}
				ref={register({ required: 'Mandatory' })}
				error={errors.address1?.message}
			/>
			<div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
				<Input
					label={intl.formatMessage({ id: 'ADDRESS_2_LABEL' })}
					name="address2"
					defaultValue={address.address2}
					ref={register()}
					error={errors.address2?.message}
				/>
				<Input
					label={intl.formatMessage({ id: 'ADDRESS_3_LABEL' })}
					name="address3"
					defaultValue={address.address3}
					ref={register()}
					error={errors.address3?.message}
				/>
			</div>
			<Input
				label={intl.formatMessage({ id: 'ZIPCODE_LABEL' })}
				name="zipCode"
				defaultValue={address.zipCode}
				ref={register({ required: 'Mandatory' })}
				error={errors.zipCode?.message}
			/>

			<Input
				label={intl.formatMessage({ id: 'CITY_LABEL' })}
				name="city"
				defaultValue={address.city}
				ref={register({ required: 'Mandatory' })}
				error={errors.city?.message}
			/>

			<Select
				label={intl.formatMessage({ id: 'COUNTRY_LABEL' })}
				name="countryCode"
				options={countries}
				defaultValue={address.countryCode}
				ref={register({ required: 'Mandatory' })}
				error={errors.countryCode?.message}
			/>

			<div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
				<Input
					label={intl.formatMessage({ id: 'CELLPHONE_LABEL' })}
					name="cellphoneNumber"
					defaultValue={address.cellphoneNumber}
					ref={register({ required: 'Mandatory' })}
					error={errors.cellphoneNumber?.message}
				/>
				<Input
					label={intl.formatMessage({ id: 'PHONE_LABEL' })}
					name="phoneNumber"
					defaultValue={address.phoneNumber}
					ref={register()}
					error={errors.phoneNumber?.message}
				/>
			</div>

			<Checkbox
				label={intl.formatMessage({ id: 'DEFAULT_ADDRESS' })}
				name="isDefault"
				ref={register()}
			/>

			<div className="mt-8 mb-3 text-center">
				<SubmitButton
					label={intl.formatMessage({ id: 'SUBMIT' })}
					isSubmitting={formState.isSubmitting}
					type="submit"
					className=""
				/>
			</div>
		</form>
	);
}
