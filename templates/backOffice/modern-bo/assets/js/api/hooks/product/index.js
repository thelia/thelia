import { getProducts } from '../../routes';
import { ReactQuery } from '@TheliaJS';

export const useGetProduct = (params) =>
  ReactQuery.useQuery(['product/search', params], () => getProducts(params));
