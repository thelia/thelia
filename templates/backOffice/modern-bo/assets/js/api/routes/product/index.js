import { fetcher } from '../../fetcher';

export const getProducts = (params = {}) =>
  fetcher('/product/search', { method: 'GET', params });
