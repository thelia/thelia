import { SubTitleProps } from './SubTitle.types';

export default function SubTitle({ subTitle, className }: SubTitleProps) {
  return (
    <div
      className={`mb-8 text-left text-3xl font-bold leading-none outline-none ${className}`}
    >
      {subTitle}
    </div>
  );
}
