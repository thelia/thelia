import { stringify } from 'qs';
import { axios } from '@thelia/utils-back';

const devPrefix = () =>
  window.location.href.includes('index_dev.php') ? '/index_dev.php' : '';

export const ApiInstance = axios.create({
  baseURL: `${devPrefix()}/open_api`,
  paramsSerializer: (params) => stringify(params, { encode: false })
});

export const fetcher = async (url, config, onlyData = true) => {
  try {
    const response = await ApiInstance(url, config);

    return onlyData ? response.data : response;
  } catch (err) {
    return Promise.reject(err);
  }
};
