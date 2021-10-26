import '../css/app.css';

import ReactDOM from 'react-dom';
import { html } from 'htm/react';
import react from 'react';

global.TheliaJS = {
  React: react,
  ReactDOM: ReactDOM,
  html: html
};
