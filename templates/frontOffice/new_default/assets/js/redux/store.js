import checkout from './modules/checkout';
import { configureStore } from '@reduxjs/toolkit';
import visibility from './modules/visibility';

export default configureStore({
  reducer: {
    visibility,
    checkout
  }
});
