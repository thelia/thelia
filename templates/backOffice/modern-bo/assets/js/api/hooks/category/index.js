import { getCategory } from '../../routes';
import { ReactQuery } from '@thelia/utils-back';

export const useGetCategory = (params) =>
  ReactQuery.useQuery(['category/search', params], () => getCategory(params));
