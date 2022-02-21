import { ReactQuery } from '@thelia/utils-back';

export const queryClient = new ReactQuery.QueryClient({
  defaultOptions: {
    queries: {
      retry: false,
      staleTime: Infinity,
      refetchOnWindowFocus: false,
      refetchOnMount: false,
      refetchInterval: false,
      suspense: false
    }
  }
});
