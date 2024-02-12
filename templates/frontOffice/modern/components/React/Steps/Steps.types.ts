export type StepsProps = {
  steps: {
    [k: string]: {
      id: number;
      slug: string;
      label: string;
      title: string;
      ctaLabel: string;
    };
  };
};
