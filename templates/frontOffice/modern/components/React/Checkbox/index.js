import React, { forwardRef } from 'react';

const Checkbox = forwardRef(({ label, name, small = false, ...props }, ref) => {
  return (
    <label className="inline-flex items-center">
      <input
        type="checkbox"
        name={name}
        {...props}
        ref={ref}
        className={`border border-gray-300 text-main focus:border-gray-300 focus:ring-main`}
      />

      <span className="ml-2">{label}</span>
    </label>
  );
});

export default Checkbox;
