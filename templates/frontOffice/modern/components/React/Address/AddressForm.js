import Checkbox from '../Checkbox';
import Input from '../Input';
import React from 'react';
import Select from '../Select';
import SubmitButton from '../SubmitButton';
import { useForm } from 'react-hook-form';
import { useIntl } from 'react-intl';

export default function AddressForm({ address = {}, onSubmit = () => {} }) {
  const intl = useIntl();
  const { register, handleSubmit, formState, setError } = useForm();
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
      className="mb-0 grid grid-cols-1 gap-6"
      onSubmit={handleSubmit(async (data) => {
        try {
          await onSubmit(data);
        } catch (error) {
          if (error?.response?.data?.schemaViolations) {
            for (const [key, val] of Object.entries(
              error?.response?.data?.schemaViolations
            )) {
              setError(key, {
                type: 'manual',
                message: val.message,
                shouldFocus: true
              });
            }
          }
        }
      })}
    >
      <Input
        label={intl.formatMessage({ id: 'LABEL_LABEL' })}
        defaultValue={address.label}
        {...register('label', {
          required: intl.formatMessage({ id: 'MANDATORY' })
        })}
        error={formState.errors?.label?.message}
      />
      <Select
        label={intl.formatMessage({ id: 'CIVILITY_TITLE_LABEL' })}
        defaultValue={address.title}
        options={titles}
        {...register('civilityTitle.id', {
          required: intl.formatMessage({ id: 'MANDATORY' })
        })}
        error={formState.errors?.title?.message}
      />
      <Input
        label={intl.formatMessage({ id: 'FIRSTNAME_LABEL' })}
        defaultValue={address.firstName}
        {...register('firstName', {
          required: intl.formatMessage({ id: 'MANDATORY' })
        })}
        error={formState.errors?.firstName?.message}
      />
      <Input
        label={intl.formatMessage({ id: 'LASTNAME_LABEL' })}
        defaultValue={address.lastName}
        {...register('lastName', {
          required: intl.formatMessage({ id: 'MANDATORY' })
        })}
        error={formState.errors?.lastName?.message}
      />
      <Input
        label={intl.formatMessage({ id: 'COMPANY_LABEL' })}
        defaultValue={address.company}
        {...register('company')}
        error={formState.errors?.company?.message}
      />

      <Input
        label={intl.formatMessage({ id: 'ADDRESS_1_LABEL' })}
        defaultValue={address.address1}
        {...register('address1', {
          required: intl.formatMessage({ id: 'MANDATORY' })
        })}
        error={formState.errors?.address1?.message}
      />
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Input
          label={intl.formatMessage({ id: 'ADDRESS_2_LABEL' })}
          defaultValue={address.address2}
          {...register('address2')}
          error={formState.errors?.address2?.message}
        />
        <Input
          label={intl.formatMessage({ id: 'ADDRESS_3_LABEL' })}
          defaultValue={address.address3}
          {...register('address3')}
          error={formState.errors?.address3?.message}
        />
      </div>
      <Input
        label={intl.formatMessage({ id: 'ZIPCODE_LABEL' })}
        defaultValue={address.zipCode}
        {...register('zipCode', {
          required: intl.formatMessage({ id: 'MANDATORY' })
        })}
        error={formState.errors?.zipCode?.message}
      />

      <Input
        label={intl.formatMessage({ id: 'CITY_LABEL' })}
        defaultValue={address.city}
        {...register('city', {
          required: intl.formatMessage({ id: 'MANDATORY' })
        })}
        error={formState.errors?.city?.message}
      />

      <Select
        label={intl.formatMessage({ id: 'COUNTRY_LABEL' })}
        options={countries}
        defaultValue={address.countryCode}
        {...register('countryCode', {
          required: intl.formatMessage({ id: 'MANDATORY' })
        })}
        error={formState.errors?.countryCode?.message}
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Input
          label={intl.formatMessage({ id: 'CELLPHONE_LABEL' })}
          defaultValue={address.cellphone}
          {...register('cellphone', {
            required: intl.formatMessage({ id: 'MANDATORY' })
          })}
          error={formState.errors?.cellphone?.message}
        />
        <Input
          label={intl.formatMessage({ id: 'PHONE_LABEL' })}
          defaultValue={address.phone}
          {...register('phone')}
          error={formState.errors?.phone?.message}
        />
      </div>

      <Checkbox
        label={intl.formatMessage({ id: 'DEFAULT_ADDRESS' })}
        {...register('isDefault')}
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
