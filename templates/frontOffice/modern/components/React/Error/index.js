import React from 'react';

export default function Error({ error }) {
  if (!error) return null;

  return (
    <span className="mb-4 block text-xs font-normal italic text-red-500">
      {error}
    </span>
  );
}
