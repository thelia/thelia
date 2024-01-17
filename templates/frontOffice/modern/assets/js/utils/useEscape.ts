import { useKey } from 'react-use';

interface useEscapeProps {
  ref: React.RefObject<HTMLElement | null>;
  handler: () => void;
}

export default function useEscape(
  ref: useEscapeProps['ref'],
  handler: useEscapeProps['handler'] = () => {}
) {
  useKey('Escape', (event) => {
    if (event?.target && ref?.current?.contains(event.target as Node)) {
      handler();
    }
  });
}
