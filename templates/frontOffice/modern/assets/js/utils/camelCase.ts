export default (str: string) =>
  `${str.charAt(0).toLowerCase()}${str
    .replace(/[\W_]/g, '|')
    .split('|')
    .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
    .join('')
    .slice(1)}`;
