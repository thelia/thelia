export type LoginFormProps = {
  setLoginHandler: React.Dispatch<React.SetStateAction<boolean>>;
  redirectionToCheckout: boolean;
};

export type MiniLoginProps = {
  isLogged: boolean;
};
