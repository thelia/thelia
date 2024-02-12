import { UseMutateFunction } from 'react-query';

export type QuantityProps = {
  mutate:
    | UseMutateFunction<any, unknown, number, unknown>
    | React.Dispatch<React.SetStateAction<number>>;
  quantity: number;
  max: number;
  title?: boolean;
  small?: boolean;
  visible?: boolean;
};
