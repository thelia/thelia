import React from 'react';

export default function Title({ title, className, step }) {
  return (
    <div className={`mb-6 text-2xl font-bold lg:text-3xl ${className || ''}`}>
      {step && <span>{step}/6</span>} {title}
    </div>
  );
}
