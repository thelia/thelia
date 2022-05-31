import React from 'react';

export default function Error({ error }) {
  if (!error) return null;

  return (
    <span className="text-red-500 text-xs font-normal italic mb-4 block">
      {error}
    </span>
  );
}
