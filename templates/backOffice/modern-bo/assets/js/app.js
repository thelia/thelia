import '../css/app.css';

import ReactDOM from 'react-dom';
import { html } from 'htm/react';
import React from 'react';
import * as ReactQuery from 'react-query';
import axios from 'axios';

export const TheliaJS = {
  React: React,
  ReactDOM: ReactDOM,
  html: html,
  ReactQuery: ReactQuery,
  axios: axios
};

export { React, ReactDOM, html, ReactQuery, axios };

export default TheliaJS;

global.TheliaJS = TheliaJS;
