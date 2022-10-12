import { useQuery } from 'react-query';
import axios from 'axios';

export const baseURL = `${window.location.href.includes('index_dev.php') ? '/index_dev.php' : ''
  }/open_api`;

export default async function fetcher(request) {
  try {
    const response = await axios(request || {});
    return response.data;
  } catch (error) {
    throw error;
  }
}

export function useProductImage(item_id) {
  return useQuery(
    ['product_img', item_id],
    () =>
      fetcher({
        url: `${baseURL}/library/image?itemId=${item_id}&itemType=product`,
      }),
    {
      enabled: !!item_id
    }
  );
}
