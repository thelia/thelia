import { React } from "react";
import { useTable, usePagination } from "react-table";

export const TableContent = ({ columns, data }) => {

      const {
        getTableProps,
        getTableBodyProps,
        headerGroups,
        page,
        nextPage,
        previousPage,
        canNextPage,
        canPreviousPage,
        pageOptions,
        state,
        prepareRow,
      } = useTable(
        {
          columns,
          data
        },
        usePagination
      );
    
      const { pageIndex } = state
    
      return (
          <>
        <table {...getTableProps()}>
          <thead>
            {headerGroups.map((headerGroup) => (
              <tr {...headerGroup.getHeaderGroupProps()}>
                {headerGroup.headers.map((column) => (
                  <th {...column.getHeaderProps()}>
                    {column.render("Header")}
                    <div>{column.canFilter ? column.render("Filter") : null}</div>
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody {...getTableBodyProps()}>
            {page.map((row, i) => {
              prepareRow(row);
              return (
                <tr {...row.getRowProps()}>
                  {row.cells.map((cell) => {
                    return <td {...cell.getCellProps()}>{cell.render("Cell")}</td>;
                  })}
                </tr>
              );
            })}
          </tbody>
        </table>
        <div>
            <button onClick={() => previousPage()} disabled={!canPreviousPage}>Précédent</button>
            <button onClick={() => nextPage()} disabled={!canNextPage}>Suivant</button>
            <span><strong>Page{' '}{pageIndex + 1} de {pageOptions.length}</strong>{' '}</span>
        </div>
        </>
      );
    }
    