import '../css/app.css';

import MiniCart from '@components/React/MiniCart';
import MiniLogin from '@components/React/MiniLogin';
import SvgAjax from '@utils/SvgAjax';
import axios from 'axios';
import * as apiUtils from '@openstudio/thelia-api-utils';

window.apiUtils = apiUtils;

function main() {
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  SvgAjax();
  MiniLogin();
  MiniCart();

  import(
    /* webpackChunkName: "Navigation" */ /* webpackPrefetch: true */ '@components/smarty/Navigation/Navigation'
  ).then(({ default: Navigation }) => Navigation());

  import(
    /* webpackChunkName: "SearchDropdown" */ /* webpackPrefetch: true */ '@components/smarty/SearchDropdown/SearchDropdown'
  ).then(({ default: SearchDropdown }) => SearchDropdown());

  import(/* webpackChunkName: "Modal" */ '@components/smarty/Modal/Modal').then(
    ({ default: Modal }) => Modal()
  );
}

document.addEventListener('DOMContentLoaded', () => {
  main();
});
