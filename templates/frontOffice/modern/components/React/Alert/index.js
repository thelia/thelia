import React from 'react';

const types = {
  success: 'bg-green-200 border-green-500 text-green-900',
  warning: 'bg-yellow-200 border-yellow-500 text-yellow-900',
  error: 'bg-red-200 border-red-500 text-red-900',
  default: 'bg-white border-white text-black'
};

export default function Alert({ type, title, message }) {
  const colorType = types[type] || types['default'];
  return (
    <div
      className={`Alert ${colorType} my-2 rounded-b border-t-4 px-4 py-3 shadow-md`}
      role="alert"
    >
      <div className="flex">
        <svg
          className="text-{$color}-900 mr-4 h-6 w-6 fill-current"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
        >
          <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z" />
        </svg>
        <div>
          {title ? <p className="font-bold">{title}</p> : null}
          {message ? <p className="text-sm">{message}</p> : null}
        </div>
      </div>
    </div>
  );
}
