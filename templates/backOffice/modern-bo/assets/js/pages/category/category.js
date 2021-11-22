import { React, ReactDOM, ReactQuery } from '@TheliaJS';
import { useGetCategory } from '../../api/hooks/category';
import { queryClient } from '../../api/queryClient';
import { parse } from 'qs';
import Loader from '../../components/Loader';

const CategoryRow = ({ id, title, isOnline, description }) => {
  return (
    <tr className="odd:bg-mediumPearl even:bg-white">
      <td className="px-5 py-4">{id}</td>
      <td className="px-5 py-4">
        <a
          href={`?parent=${id}`}
          className="text-main hover:text-darkMain font-semibold"
        >
          {title}
        </a>
      </td>
      <td className="px-5 py-4">
        <label
          htmlFor={`rowToggle-${id}`}
          className="flex items-center justify-center cursor-pointer"
        >
          <div className="relative">
            <input
              type="checkbox"
              defaultChecked={isOnline}
              id={`rowToggle-${id}`}
              className="switch sr-only"
            />
            <div className="block bg-red-500 w-10 h-6 rounded-full"></div>
            <div className="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition"></div>
          </div>
        </label>
      </td>
      <td className="px-5 py-4">{description}</td>
      <td className="py-4 px-auto flex justify-center">
        <a
          href="/"
          className="flex items-center justify-center p-3 text-center bg-yellow-700 hover:bg-yellow-800 text-white hover:text-lightGrey font-medium"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="far fa-folder"></i>
        </a>

        <a
          href="/"
          className="flex items-center justify-center p-3 text-center bg-indigo-700 hover:bg-indigo-800 text-white hover:text-lightGrey font-medium"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="fas fa-pen"></i>
        </a>

        <a
          href="/"
          className="flex items-center justify-center p-3 text-center bg-red-700 hover:bg-red-800 text-white hover:text-lightGrey font-medium"
          title="{intl l='View site'}"
          target="_blank"
        >
          <i className="fas fa-trash"></i>
        </a>
      </td>
    </tr>
  );
};

const Category = () => {
  const params = parse(window.location.search, { ignoreQueryPrefix: true });

  const { data = [], isLoading } = useGetCategory({
    parentsIds: params.parent || 0
  });

  return (
    <div className="bg-white rounded-lg shadow-xl p-5">
      <div className="flex justify-between mb-10">
        <h1 className="text-2xl font-semibold">Liste des categories</h1>
        <div class="h-10 w-10 bg-main hover:bg-darkMain text-white hover:text-lightGrey p-4 flex items-center justify-center rounded-full font-medium mr-3">
          <i class="fas fa-sign-out-alt"></i>
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center">
          <Loader />
        </div>
      ) : (
        <table className="table-auto bg-lightPearl rounded-xl w-full shadow-lg">
          <thead className="bg-mediumCharbon text-lightGrey">
            <tr>
              <th className="px-5 py-4 text-left">ID</th>
              <th className="px-5 py-4 text-left">Titre</th>
              <th className="px-5 py-4">En ligne</th>
              <th className="px-5 py-4 text-left">Description</th>
              <th className="px-5 py-4">Action</th>
            </tr>
          </thead>
          <tbody>
            {data?.map((category, index) => (
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
      )}
    </div>
  );
};

ReactDOM.render(
  <ReactQuery.QueryClientProvider client={queryClient}>
    <Category />
  </ReactQuery.QueryClientProvider>,
  document.querySelector('#categories')
);
