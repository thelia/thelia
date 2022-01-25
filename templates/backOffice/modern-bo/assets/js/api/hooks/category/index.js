import { getCategory } from '../../routes';
import { ReactQuery } from '@TheliaJS';

export const useGetCategory = (params) =>
  ReactQuery.useQuery(['category/search', params], () => getCategory(params));
