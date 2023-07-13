import { TotalProps } from '../MiniCart.types';

function Total({ label, value }: TotalProps) {
  return (
    <dl className="flex flex-col items-baseline justify-between leading-none">
      <dt className="text-sm text-gray-600">{label}</dt>
      <dd className="text-3xl font-medium">{value}</dd>
    </dl>
  );
}

export default Total;
