import Loader from '../Loader';
import React from 'react';

export default function SubmitButton({
  label,
  isSubmitting,
  onClick = () => { },
  className,
  ...props
}) {
  return (
    <button
      className={`Button relative ${className ? className : ''} ${isSubmitting ? 'Button--loading' : ''}`}
      onClick={onClick}
      type="button"
      {...props}
    >
      {isSubmitting ? (
        <Loader className="absolute w-8 h-8 text-white transform -translate-x-1/2 -translate-y-1/2 left-1/2 top-1/2" />
      ) : null}

      <div className={`${isSubmitting ? 'opacity-0' : ''}`}>{label}</div>
    </button>
  );
}
