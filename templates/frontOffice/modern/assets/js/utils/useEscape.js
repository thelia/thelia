import { useKey } from 'react-use';

export default function useEscape(ref = null, handler = () => {}) {
  useKey('Escape', (e) => {
    if (ref?.current.contains(e.target)) {
      handler();
    }
  });
}
