import '../css/app.css';

import MiniCart from '@components/React/MiniCart';
import MiniLogin from '@components/React/MiniLogin';
import SvgAjax from '@utils/SvgAjax';
import PasswordSwitcher from '@standalone/PasswordSwitcher';
import OrderDetailsButton from '@standalone/OrderDetailsButton';
import axios from 'axios';

import * as apiUtils from '@openstudio/thelia-api-utils';
import Header from '@components/smarty/Header/Header';
import Newsletter from '@components/smarty/Newsletter/Newsletter';

window.apiUtils = apiUtils;

function main() {
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  SvgAjax();
  MiniLogin();
  MiniCart();
  PasswordSwitcher();
  OrderDetailsButton();
  Header();
  Newsletter();

  import(
    /* webpackChunkName: "SearchDropdown" */ /* webpackPrefetch: true */ '@components/smarty/SearchDropdown/SearchDropdown'
  ).then(({ default: SearchDropdown }) => SearchDropdown());

  import(/* webpackChunkName: "Modal" */ '@components/smarty/Modal/Modal').then(
    ({ default: Modal }) => Modal()
  );
  import(
    /* webpackChunkName: "Navigation" */ /* webpackPrefetch: true */ '@components/smarty/Navigation/Navigation'
  )
    .then(({ default: Navigation }) => Navigation())
    .then(() => document.body.classList.remove('no-js'));
}

document.addEventListener('DOMContentLoaded', () => {
  main();
});
