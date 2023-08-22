import Loader from '../Loader';
import { SubmitButtonProps } from './SubmitButton.types';

export default function SubmitButton({
  label,
  isSubmitting,
  onClick = () => {},
  className,
  ...props
}: SubmitButtonProps) {
  return (
    <button
      className={`Button relative ${className ? className : ''} ${
        isSubmitting ? 'Button--loading' : ''
      }`}
      onClick={onClick}
      type="button"
      {...props}
    >
      {isSubmitting ? (
        <Loader className="absolute left-1/2 top-1/2 h-8 w-8 -translate-x-1/2 -translate-y-1/2 transform text-white" />
      ) : null}

      <div className={`${isSubmitting ? 'opacity-0' : ''}`}>{label}</div>
    </button>
  );
}
