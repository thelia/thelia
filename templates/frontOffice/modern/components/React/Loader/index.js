import React from 'react';
import { ReactComponent as IconLoader } from '@icons/loader.svg';

function Loader({ color = '#76b82a', className = 'w-40' }) {
  return (
    <div className={`${className} mx-auto block`}>
      <IconLoader className="h-6 w-6" />
    </div>
  );
}

export default Loader;
