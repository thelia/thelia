import React from 'react';
import { useIntl } from 'react-intl';

import { StepsProps } from './Steps.types';
import { useGlobalCheckout } from '@js/state/checkout';

export default function Steps({ steps }: StepsProps) {
  const intl = useIntl();

  const { checkoutState, actions } = useGlobalCheckout();
  const { checkoutStep } = checkoutState;

  return (
    <nav className="Steps">
      <ul className="Steps-list">
        {Object.values(steps).map((step, index) => {
          return (
            <li
              key={index}
              onClick={() => actions.setCheckoutStep(step.id)}
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
