import { fetcher } from '../../fetcher';

export const getCategory = (params = {}) =>
  fetcher('/category/search', { method: 'GET', params });
