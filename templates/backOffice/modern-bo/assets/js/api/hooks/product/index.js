import { getProducts } from '../../routes';
import { ReactQuery } from '@thelia/utils-back';

export const useGetProduct = (params) =>
  ReactQuery.useQuery(['product/search', params], () => getProducts(params));
