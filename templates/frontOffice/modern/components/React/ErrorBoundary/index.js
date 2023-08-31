import Alert from '../Alert';
import React from 'react';

export default class ErrorBoundary extends React.Component {
  constructor() {
    super();
    this.state = {
      error: null
    };
  }

  componentDidCatch(error) {
    console.error(error);
    this.setState({ error });
  }
  render() {
    if (this.state.error) {
      if (typeof this.props.fallback === 'function') {
        return this.props.fallback(this.state.error?.message);
      } else if (this.props.fallback) {
        return this.props.fallback;
      }
      return <Alert type="error" title="Error" />;
    }
    return this.props.children;
  }
}
