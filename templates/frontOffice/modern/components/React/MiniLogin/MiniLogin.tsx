import { IntlProvider, useIntl } from 'react-intl';
import { Provider, useSelector, useDispatch } from 'react-redux';
import React, { useEffect, useState, useRef, useLayoutEffect } from 'react';
import { hideLogin, toggleLogin } from '@js/redux/modules/visibility';
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
import store from '@js/redux/store';
import Alert from '../Alert';
import useLockBodyScroll from '@utils/useLockBodyScroll';
import { useClickAway } from 'react-use';
import useEscape from '@js/utils/useEscape';
import closeAndFocus from '@js/utils/closeAndFocus';
import { trapTabKey } from '@js/standalone/trapItemsMenu';
import { LoginFormProps, MiniLoginProps } from './MiniLogin.types';

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
  const dispatch = useDispatch();
  const { login: visible, redirectionToCheckout } = useSelector(
    (state: any) => state.visibility
  );
  const [loginHandler, setLoginHandler] = useState(isLogged);
  const { data: customer, isLoading } = useCustomer(loginHandler);
  const ref = useRef<HTMLDivElement>(null);
  const focusRef = useRef<HTMLButtonElement | null>(null);

  useLayoutEffect(() => {
    if (visible && focusRef.current) {
      focusRef.current.focus();
    }
  }, [focusRef, visible]);

  useEffect(() => {
    if (visible && redirectionToCheckout && customer) {
      (window as any).location = `${window.location.origin}/order/delivery`;
    }
  }, [visible, redirectionToCheckout, customer]);

  useLockBodyScroll(ref, visible, redirectionToCheckout);

  useClickAway(ref, (e) => {
    if (!(e.target as HTMLElement)?.matches('[data-toggle-login]') && visible) {
      closeAndFocus(
        () => dispatch(hideLogin({ redirectionToCheckout: false })),
        '[data-toggle-login]'
      );
    }
  });

  useEffect(() => {
    focusRef?.current?.focus();
  }, [focusRef]);

  useEscape(ref, () =>
    closeAndFocus(
      () => dispatch(hideLogin({ redirectionToCheckout: false })),
      '[data-toggle-login]'
    )
  );

  ref?.current?.addEventListener('keydown', (e) => {
    trapTabKey(ref.current, e);
  });

  return (
    <div ref={ref} className={`SideBar ${visible ? 'SideBar--visible' : ''}`}>
      <button
        ref={focusRef}
        onClick={() =>
          closeAndFocus(
            () => dispatch(hideLogin({ redirectionToCheckout: false })),
            '[data-toggle-login]'
          )
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

export default function MiniLoginRender() {
  const isLogged =
    (document.querySelector('.MiniLogin-root') as HTMLElement)?.dataset
      ?.login === 'true' || false;

  document.addEventListener(
    'click',
    (e) => {
      if ((e.target as HTMLElement)?.matches('[data-toggle-login]')) {
        e.preventDefault();
        store.dispatch(toggleLogin());
      } else if ((e.target as HTMLElement)?.matches('[data-close-login]')) {
        e.preventDefault();
        store.dispatch(hideLogin({ redirectionToCheckout: false }));
      }
    },
    false
  );

  const DOMElement = document.querySelector('.MiniLogin-root');

  if (!DOMElement) return;

  const root = createRoot(DOMElement);

  root.render(
    <QueryClientProvider client={queryClient}>
      <IntlProvider locale={locale} messages={(messages as any)[locale]}>
        <Provider store={store}>
          <MiniLogin isLogged={isLogged} />
        </Provider>
      </IntlProvider>
    </QueryClientProvider>
  );
}
