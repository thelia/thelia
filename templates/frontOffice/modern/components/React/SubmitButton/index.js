import Loader from '../Loader';
import React from 'react';

export default function SubmitButton({
  label,
  isSubmitting,
  onClick = () => {},
  className,
  ...props
}) {
  return (
    <button
      className={`btn relative ${className ? className : ''}`}
      onClick={onClick}
      type="button"
      {...props}
    >
      {isSubmitting ? (
        <div className="absolute inset-0 flex items-center justify-center">
          <Loader size="w-6 h-6" color="text-white" />
        </div>
      ) : null}

      <div className={`${isSubmitting ? 'opacity-0' : ''}`}>{label}</div>
    </button>
  );
}
