import React, { forwardRef } from 'react';

const Checkbox = forwardRef(({ label, name, small = false, className = '', ...props }, ref) => {
  return (
    <label className={`Checkbox ${className ? className : ''}`}>
      <input
        type="checkbox"
        name={name}
        {...props}
        ref={ref}
      />

      <span>{label}</span>
    </label>
  );
});

export default Checkbox;
