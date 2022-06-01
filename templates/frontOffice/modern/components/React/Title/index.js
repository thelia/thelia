import React from 'react';

export default function Title({ title, className }) {
  return <div className={`Title ${className}`}>{title}</div>;
}
