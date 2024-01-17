import { IntlProvider, useIntl } from 'react-intl';

import { useEffect, useState, useRef, useLayoutEffect } from 'react';

import messages, { locale } from '../intl';
import {
  useCustomer,
  useLogin,
  queryClient
} from '@openstudio/thelia-api-utils';
import { ReactComponent as IconCLose } from '@icons/close.svg';
import Input from '../Input';
import Loader from '../Loader';
import { QueryClientProvider } from 'react-query';

import { createRoot } from 'react-dom/client';

import Alert from '../Alert';
import useLockBodyScroll from '@utils/useLockBodyScroll';
import { useClickAway } from 'react-use';
import useEscape from '@js/utils/useEscape';
import closeAndFocus from '@js/utils/closeAndFocus';
import { trapTabKey } from '@js/standalone/trapItemsMenu';
import { LoginFormProps, MiniLoginProps } from './MiniLogin.types';
import { useGlobalVisibility } from '@js/state/visibility';

function LoginForm({ setLoginHandler, redirectionToCheckout }: LoginFormProps) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const {
    mutateAsync: login,
    isLoading,
    error
  } = useLogin(!redirectionToCheckout);
  const intl = useIntl();

  return (
    <form
      className="border-b border-gray-300 pb-[52px]"
      onSubmit={async (e) => {
        e.preventDefault();
        await login({ email, password, rememberMe: true });
        setLoginHandler(true);
      }}
    >
      <legend className="Title Title--3 mb-6">
        {intl.formatMessage({ id: 'ALREADY_CUSTOMER' })}
      </legend>
      <div className="mb-6 grid grid-cols-1 gap-6">
        <Input
          type="email"
          name="email"
          className=""
          label="E-mail"
          value={email}
          autoComplete="email"
          onChange={(e) => setEmail(e.target.value)}
        />

        <Input
          type="password"
          name="password"
          className=""
          label="Mot de passe"
          autoComplete="current-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
      </div>

      {error ? (
        <Alert
          type="error"
          message={(error as any)?.response?.data?.description || 'Erreur'}
        />
      ) : null}
      <div className="mt-6 flex flex-wrap items-center justify-between gap-4">
        <button
          type="submit"
          className="Button"
          disabled={isLoading || !password || !email}
        >
          {isLoading
            ? intl.formatMessage({ id: 'LOADING' })
            : intl.formatMessage({ id: 'LOGIN' })}
        </button>

        <a href="/password" className="underline">
          {intl.formatMessage({ id: 'FORGET_PASSWORD' })}{' '}
        </a>
      </div>
    </form>
  );
}

function IsLoggedOut({
  setLoginHandler,
  redirectionToCheckout
}: LoginFormProps) {
  const intl = useIntl();

  return (
    <div className="h-full overflow-auto">
      <LoginForm
        setLoginHandler={setLoginHandler}
        redirectionToCheckout={redirectionToCheckout}
      />
      <fieldset className="pt-12">
        <span className="Title Title--3 mb-5 block">
          {intl.formatMessage({ id: 'NEW_CUSTOMER' })}
        </span>
        <a href="/register" className="Button mb-20 inline-block">
          {intl.formatMessage({ id: 'CREATE_ACCOUNT' })}
        </a>
      </fieldset>
    </div>
  );
}

export function MiniLogin({ isLogged }: MiniLoginProps) {
  const { visibilityState, actions } = useGlobalVisibility();
  const { login: visible, redirectionToCheckout } = visibilityState;

  const [loginHandler, setLoginHandler] = useState(isLogged);
  const { data: customer, isLoading } = useCustomer(loginHandler);
  const ref = useRef<HTMLDivElement>(null);
  const focusRef = useRef<HTMLButtonElement | null>(null);

  useLayoutEffect(() => {
    if (visible && focusRef.current) {
      focusRef.current.focus();
    }
  }, [focusRef, visible]);

  useLockBodyScroll(ref, visible, redirectionToCheckout);

  useClickAway(ref, (e) => {
    if (!(e.target as HTMLElement)?.matches('[data-toggle-login]') && visible) {
      closeAndFocus(() => actions.hideLogin(false), '[data-toggle-login]');
    }
  });

  useEffect(() => {
    focusRef?.current?.focus();
  }, [focusRef]);

  useEscape(ref, () =>
    closeAndFocus(() => actions.hideLogin(false), '[data-toggle-login]')
  );

  useEffect(() => {
    const onKeydown = (e: KeyboardEvent) => {
      trapTabKey(ref.current as HTMLElement, e);
    };
    ref?.current?.addEventListener('keydown', onKeydown);

    return () => {
      ref?.current?.removeEventListener('keydown', onKeydown);
    };
  }, []);

  return (
    <div ref={ref} className={`SideBar ${visible ? 'SideBar--visible' : ''}`}>
      <button
        ref={focusRef}
        onClick={() =>
          closeAndFocus(() => actions.hideLogin(false), '[data-toggle-login]')
        }
        type="button"
        className="SideBar-close"
        aria-label="Fermer le formulair de connexion"
      >
        <IconCLose className="pointer-events-none h-3 w-3" />
      </button>
      {isLoading ? (
        <Loader />
      ) : !customer ? (
        <IsLoggedOut
          setLoginHandler={setLoginHandler}
          redirectionToCheckout={redirectionToCheckout}
        />
      ) : (
        <Loader />
      )}
    </div>
  );
}

export function MiniLoginWrapper() {
  const { visibilityState, actions } = useGlobalVisibility();

  const isLogged =
    (document.querySelector('.MiniLogin-root') as HTMLElement)?.dataset
      ?.login === 'true' || false;

  useEffect(() => {
    const onClick = (e: Event) => {
      if ((e.target as HTMLElement)?.matches('[data-toggle-login]')) {
        e.preventDefault();
        actions.toggleLogin();
      } else if ((e.target as HTMLElement)?.matches('[data-close-login]')) {
        e.preventDefault();
        actions.hideLogin(false);
      }
    };
    document.addEventListener('click', onClick, false);

    return () => {
      document.removeEventListener('click', onClick);
    };
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <IntlProvider locale={locale} messages={messages[locale]}>
        <MiniLogin isLogged={isLogged} />
      </IntlProvider>
    </QueryClientProvider>
  );
}

const MiniLoginRender = () => {
  const DOMElement = document.querySelector('.MiniLogin-root');

  if (!DOMElement) return;

  const root = createRoot(DOMElement);

  root.render(<MiniLoginWrapper></MiniLoginWrapper>);
};

export default MiniLoginRender;
