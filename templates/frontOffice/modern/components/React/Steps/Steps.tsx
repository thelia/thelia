import React from 'react';
import { useIntl } from 'react-intl';
import { useDispatch, useSelector } from 'react-redux';
import { setCheckoutStep } from '@js/redux/modules/checkout';
import { StepsProps } from './Steps.types';

export default function Steps({ steps }: StepsProps) {
  const intl = useIntl();
  const dispatch = useDispatch();
  const { checkoutStep } = useSelector((state: any) => state.checkout);

  return (
    <nav className="Steps">
      <ul className="Steps-list">
        {Object.values(steps).map((step, index) => {
          return (
            <li
              key={index}
              onClick={() => dispatch(setCheckoutStep(step.id))}
              className={`Steps-item ${
                step.id === checkoutStep ? 'Steps-item--active' : ''
              }`}
            >
              <span className="Steps-number">{step.id}</span>
              <span className="Steps-name">
                {intl.formatMessage({ id: `${step.label}` })}
              </span>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
