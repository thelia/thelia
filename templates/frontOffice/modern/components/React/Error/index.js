import React from 'react';

export default function Error({ error }) {
  if (!error) return null;

  return (
    <span className="Input-message">
      {error}
    </span>
  );
}
