import { React, ReactDOM, ReactQuery } from '@TheliaJS';
import { useGetCategory } from '../../api/hooks';
import { useGetProduct } from '../../api/hooks';
import { queryClient } from '../../api/queryClient';
import { parse } from 'qs';
import Loader from '../../components/Loader';

const CatalogBreadCrumb = ({ parentCategory }) => {
  return (
    <ul className="flex mb-4">
      <li>
        <a className="text-main font-bold" href="home">
          Accueil
        </a>
      </li>
      <li className="mx-2">/</li>
      <li>
        <a className="text-main font-bold" href="catalog">
          Catalogue
        </a>
      </li>
      {parentCategory[0]?.id && (
        <>
          <li className="mx-2">/</li>
          <li>
            <a className="text-main font-bold" href="catalog">
              {parentCategory[0]?.i18n?.title}
            </a>
          </li>
        </>
      )}
    </ul>
  );
};

const CategoryRow = ({ id, title, isOnline, description }) => {
  const handleStateChange = (e) => {
    console.log(e);
  };

  return (
    <tr className="odd:bg-mediumPearl even:bg-white">
      <td className="px-5 py-4">{id}</td>
      <td className="px-5 py-4">
        <a
          href={`?category_id=${id}`}
          className="text-main hover:text-darkMain font-bold"
        >
          {title}
        </a>
      </td>
      <td className="px-5 py-4">
        <label
          htmlFor={`category-${id}-toggle`}
          className="flex items-center justify-center cursor-pointer"
        >
          <div className="relative">
            <input
              type="checkbox"
              defaultChecked={isOnline}
              onChange={handleStateChange}
              id={`category-${id}-toggle`}
              className="switch sr-only"
            />
            <div className="block bg-red-500 w-10 h-6 rounded-full"></div>
            <div className="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition"></div>
          </div>
        </label>
      </td>
      <td className="px-5 py-4 w-2/3">{description}</td>
      <td className="px-5 py-4 px-auto flex justify-center">
        <button
          href="/"
          className="flex items-center justify-center p-3 text-center bg-carot text-white hover:text-lightGrey font-medium rounded-md"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="fas fa-folder-open"></i>
        </button>

        <button
          href="/"
          className="flex items-center justify-center mx-1 p-3 text-center bg-blue text-white hover:text-lightGrey font-medium rounded-md"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="fas fa-edit"></i>
        </button>

        <button
          href="/"
          className="flex items-center justify-center p-3 text-center bg-error text-white hover:text-lightGrey font-medium rounded-md"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="fas fa-trash-alt"></i>
        </button>
      </td>
    </tr>
  );
};

const ProductRow = ({ id, title, isOnline, reference, price }) => {
  const handleStateChange = (e) => {
    console.log(e);
  };

  return (
    <tr className="odd:bg-mediumPearl even:bg-white">
      <td className="px-5 py-4">{id}</td>
      <td className="px-5 py-4">
        <a href="/" className="text-main hover:text-darkMain font-bold">
          {reference}
        </a>
      </td>
      <td className="px-5 py-4">
        <a href="/" className="text-main hover:text-darkMain font-bold">
          {title}
        </a>
      </td>
      <td className="px-5 py-4">{price} €</td>
      <td className="px-5 py-4">
        <label
          htmlFor={`product-${id}-toggle`}
          className="flex items-center justify-center cursor-pointer"
        >
          <div className="relative">
            <input
              type="checkbox"
              defaultChecked={isOnline}
              onChange={handleStateChange}
              id={`product-${id}-toggle`}
              className="switch sr-only"
            />
            <div className="block bg-red-500 w-10 h-6 rounded-full"></div>
            <div className="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition"></div>
          </div>
        </label>
      </td>
      <td className="py-4 px-auto flex justify-center">
        <button
          href="/"
          className="flex items-center justify-center p-3 text-center bg-carot text-white hover:text-lightGrey font-medium rounded-md"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="fas fa-folder-open"></i>
        </button>

        <button
          href="/"
          className="flex items-center justify-center mx-1 p-3 text-center bg-blue text-white hover:text-lightGrey font-medium rounded-md"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="fas fa-edit"></i>
        </button>

        <button
          href="/"
          className="flex items-center justify-center p-3 text-center bg-error text-white hover:text-lightGrey font-medium rounded-md"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="fas fa-trash-alt"></i>
        </button>
      </td>
    </tr>
  );
};

const Categories = ({ params, parentCategory }) => {
  const { data: categories = [], isLoading } = useGetCategory({
    parentsIds: params.category_id || 0
  });

  return (
    <div className="bg-white rounded-lg shadow-xl p-5 mb-10">
      <div className="flex justify-between mb-5">
        <h1 className="text-2xl font-semibold">
          Liste des rubriques{' '}
          {parentCategory[0]?.id && parentCategory[0]?.i18n?.title
            ? `dans ${parentCategory[0]?.i18n?.title}`
            : null}
        </h1>
        <button className="h-10 w-10 bg-main hover:bg-darkMain text-white hover:text-lightGrey p-4 flex items-center justify-center rounded-full font-medium mr-3">
          <i className="fas fa-plus"></i>
        </button>
      </div>

      {isLoading ? (
        <div className="flex justify-center">
          <Loader />
        </div>
      ) : categories.length > 0 ? (
        <table className="table-auto bg-lightPearl w-full shadow-lg">
          <thead className="bg-mediumCharbon text-lightGrey">
            <tr>
              <th className="px-5 py-4 text-left rounded-tl-xl">ID</th>
              <th className="px-5 py-4 text-left">Titre</th>
              <th className="px-5 py-4">En ligne</th>
              <th className="px-5 py-4 text-left">Description</th>
              <th className="px-5 py-4 rounded-tr-xl">Actions</th>
            </tr>
          </thead>
          <tbody>
            {categories?.map((category, index) => (
              <CategoryRow
                key={index}
                id={category.id}
                title={category.i18n.title}
                isOnline={category.visible}
                description={category.i18n.description}
              />
            ))}
          </tbody>
        </table>
      ) : (
        <div className="w-full mx-auto rounded-lg bg-info text-blue text-center mt-10 p-5 border-blue border">
          Cette rubrique n'a pas de sous-rubrique. Pour en créer une nouvelle,{' '}
          <strong>cliquez sur le bouton +</strong> ci-dessus.
        </div>
      )}
    </div>
  );
};

const Products = ({ params, parentCategory }) => {
  const { data: products = [], isLoading } = useGetProduct({
    id: params.category_id
  });

  return (
    <div className="bg-white rounded-lg shadow-xl p-5">
      <div className="flex justify-between mb-5">
        <h1 className="text-2xl font-semibold">
          Liste des produits{' '}
          {parentCategory[0]?.id && parentCategory[0]?.i18n?.title
            ? `dans ${parentCategory[0]?.i18n?.title}`
            : null}
        </h1>
        <button className="h-10 w-10 bg-main hover:bg-darkMain text-white hover:text-lightGrey p-4 flex items-center justify-center rounded-full font-medium mr-3">
          <i className="fas fa-plus"></i>
        </button>
      </div>

      {isLoading ? (
        <div className="flex justify-center">
          <Loader />
        </div>
      ) : products?.length > 0 ? (
        <table className="table-auto bg-lightPearl w-full shadow-lg">
          <thead className="bg-mediumCharbon text-lightGrey">
            <tr>
              <th className="px-5 py-4 text-left rounded-tl-xl">ID</th>
              <th className="px-5 py-4 text-left">Référence</th>
              <th className="px-5 py-4 text-left">Titre du produit</th>
              <th className="px-5 py-4 text-left">Prix</th>
              <th className="px-5 py-4">En ligne</th>
              <th className="px-5 py-4 rounded-tr-xl">Actions</th>
            </tr>
          </thead>
          <tbody>
            {products?.map((product, index) => (
              <ProductRow
                key={index}
                id={product.id}
                title={product.i18n.title}
                isOnline={product.visible}
                reference={product.reference}
                price={product.productSaleElements[0].price.untaxed}
              />
            ))}
          </tbody>
        </table>
      ) : (
        <div className="w-full mx-auto rounded-lg bg-info text-blue text-center mt-10 p-5 border-blue border">
          Cette rubrique n'a aucun produit. Pour créer un nouveau produit,{' '}
          <strong>cliquez sur le bouton +</strong> ci-dessus.
        </div>
      )}
    </div>
  );
};

const Catalog = () => {
  const params = parse(window.location.search, { ignoreQueryPrefix: true });

  const { data: parentCategory = [] } = useGetCategory({
    id: params.category_id || 0
  });

  return (
    <>
      <CatalogBreadCrumb parentCategory={parentCategory} />
      <Categories params={params} parentCategory={parentCategory} />
      {params.category_id ? (
        <Products params={params} parentCategory={parentCategory} />
      ) : (
        <div className="w-full mx-auto rounded-lg bg-info text-blue text-center mt-10 p-5 border-blue border">
          Pour créer un nouveau produit, veuillez sélectionner une rubrique
          existante ou en créer une nouvelle
        </div>
      )}
    </>
  );
};

ReactDOM.render(
  <ReactQuery.QueryClientProvider client={queryClient}>
    <Catalog />
  </ReactQuery.QueryClientProvider>,
  document.querySelector('#categories')
);
