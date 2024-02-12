import '../css/app.css';

import * as apiUtils from '@openstudio/thelia-api-utils';

import Header from '@components/smarty/Header/Header';
import MiniCart from '@components/React/MiniCart';
import MiniLogin from '@components/React/MiniLogin';
import Modal from '@components/smarty/Modal/Modal';
import Navigation from '@components/smarty/Navigation/Navigation';
import Newsletter from '@components/smarty/Newsletter/Newsletter';
import OrderDetailsButton from '@standalone/OrderDetailsButton';
import PasswordSwitcher from '@standalone/PasswordSwitcher';
import SearchDropdown from '@components/smarty/SearchDropdown/SearchDropdown';
import SvgAjax from '@utils/SvgAjax';
import axios from 'axios';

window.apiUtils = apiUtils;

function main() {
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

  document.body.classList.remove('no-js');

  SvgAjax();
  MiniLogin();
  MiniCart();
  PasswordSwitcher();
  OrderDetailsButton();
  Header();
  Newsletter();
  SearchDropdown();
  Modal();
  Navigation();
}

document.addEventListener('DOMContentLoaded', () => {
  main();
});
